<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GoCache Demo</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px; background: #f4f4f4; }
        #chatbox { list-style: none; padding: 0; margin: 0 0 20px 0; height: 300px; overflow-y: scroll; border: 1px solid #ccc; background: #fff; padding: 10px; }
        #chatbox li { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        #chatbox li strong { color: #007bff; }
        form { display: flex; }
        input { flex-grow: 1; border: 1px solid #ccc; padding: 10px; }
        button { border: none; background: #007bff; color: white; padding: 10px 15px; cursor: pointer; }
        #status { text-align: center; padding: 10px; color: #888; height: 20px; }
    </style>
</head>
<body>
    <h1>GoCache Shoutbox</h1>
    <div id="status">Loading messages...</div>
    <ul id="chatbox"></ul>
    <form id="messageForm">
        <input type="text" id="messageText" placeholder="Type a message..." required>
        <button type="submit">Send</button>
    </form>

    <script>
        const chatbox = document.getElementById('chatbox');
        const messageForm = document.getElementById('messageForm');
        const messageText = document.getElementById('messageText');
        const status = document.getElementById('status');

        async function fetchMessages() {
            status.textContent = 'Fetching messages... (might be slow)';
            const startTime = Date.now();

            const response = await fetch('api.php?action=get_messages');
            const messages = await response.json();

            chatbox.innerHTML = '';
            for (const msg of messages) {
                const li = document.createElement('li');
                const date = new Date(msg.time * 1000).toLocaleTimeString();
                li.innerHTML = `<strong>${msg.user.name}</strong> (${date}):<br>${msg.text}`;
                chatbox.appendChild(li);
            }
            
            const duration = (Date.now() - startTime) / 1000;
            status.textContent = `Feed updated in ${duration.toFixed(2)} seconds.`;
        }

        messageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = messageText.value;
            if (!text) return;

            status.textContent = 'Sending...';
            const userId = (chatbox.children.length % 2 === 0) ? 1 : 2;

            await fetch('api.php?action=post_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: text, userId: userId })
            });

            messageText.value = '';
            await fetchMessages();
        });

        fetchMessages();
    </script>
</body>
</html>