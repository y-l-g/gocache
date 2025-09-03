package gocache

//#include "gocache.h"
import "C"
import (
	"sync"
	"time"
	"unsafe"

	"github.com/dunglas/frankenphp"
)

type cacheItem struct {
	value     []byte
	expiresAt int64
}

type CacheEngine struct {
	mu      sync.RWMutex
	items   map[string]cacheItem
	janitor *janitor
}

func NewCacheEngine(janitorInterval time.Duration) *CacheEngine {
	e := &CacheEngine{
		items: make(map[string]cacheItem),
	}
	if janitorInterval > 0 {
		e.janitor = newJanitor(e, janitorInterval)
		e.janitor.Run()
	}
	return e
}

func (e *CacheEngine) Set(key string, value []byte, ttl time.Duration) {
	var expires int64
	if ttl > 0 {
		expires = time.Now().Add(ttl).UnixNano()
	}
	e.mu.Lock()
	e.items[key] = cacheItem{
		value:     value,
		expiresAt: expires,
	}
	e.mu.Unlock()
}

// THIS IS THE CORRECTED FUNCTION
func (e *CacheEngine) Get(key string) ([]byte, bool) {
	e.mu.RLock()
	defer e.mu.RUnlock()

	item, found := e.items[key]
	if !found {
		return nil, false
	}

	if item.expiresAt > 0 && time.Now().UnixNano() > item.expiresAt {
		return nil, false
	}

	return item.value, true
}

func (e *CacheEngine) Delete(key string) {
	e.mu.Lock()
	delete(e.items, key)
	e.mu.Unlock()
}

func (e *CacheEngine) deleteExpired() {
	now := time.Now().UnixNano()
	e.mu.Lock()
	var keysToDelete []string
	for k, v := range e.items {
		if v.expiresAt > 0 && now > v.expiresAt {
			keysToDelete = append(keysToDelete, k)
		}
	}
	for _, k := range keysToDelete {
		delete(e.items, k)
	}
	e.mu.Unlock()
}

type janitor struct {
	engine   *CacheEngine
	interval time.Duration
	stop     chan bool
}

func newJanitor(e *CacheEngine, ci time.Duration) *janitor {
	return &janitor{
		engine:   e,
		interval: ci,
		stop:     make(chan bool),
	}
}

func (j *janitor) Run() {
	ticker := time.NewTicker(j.interval)
	go func() {
		for {
			select {
			case <-ticker.C:
				j.engine.deleteExpired()
			case <-j.stop:
				ticker.Stop()
				return
			}
		}
	}()
}

var engine *CacheEngine

func init() {
	engine = NewCacheEngine(1 * time.Minute)
	frankenphp.RegisterExtension(unsafe.Pointer(&C.gocache_module_entry))
}

//export go_get
func go_get(key *C.zend_string) *C.zend_string {
	goKey := frankenphp.GoString(unsafe.Pointer(key))
	value, found := engine.Get(goKey)

	if !found {
		return nil
	}
	return (*C.zend_string)(frankenphp.PHPString(string(value), false))
}

//export go_set
func go_set(key *C.zend_string, value *C.zend_string, ttl int64) bool {
	goKey := frankenphp.GoString(unsafe.Pointer(key))
	goValue := []byte(frankenphp.GoString(unsafe.Pointer(value)))

	engine.Set(goKey, goValue, time.Duration(ttl)*time.Second)
	return true
}

//export go_forget
func go_forget(key *C.zend_string) bool {
	goKey := frankenphp.GoString(unsafe.Pointer(key))
	engine.Delete(goKey)
	return true
}