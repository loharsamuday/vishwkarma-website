<?php
$page_title = "Class Group Chat";
require_once 'includes/db.php';
require_once 'includes/session.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Fetch user profile and check if student
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.role_id, sp.class_name FROM users u LEFT JOIN student_profiles sp ON u.id = sp.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data || $user_data['role_id'] != 4 || empty($user_data['class_name'])) {
    setFlashMessage('error', 'This area is only for registered students.');
    header("Location: dashboard.php");
    exit;
}

$class_name = $user_data['class_name'];

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow border-0" style="border-radius: 15px; overflow: hidden;">
                <!-- Chat Header -->
                <div class="card-header bg-primary text-white p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-users me-2"></i> <?= htmlspecialchars($class_name) ?> - Group Chat</h5>
                        <small class="opacity-75">Only students of <?= htmlspecialchars($class_name) ?> can see this</small>
                    </div>
                </div>

                <!-- Chat Messages Area -->
                <div class="card-body bg-light" id="chatBox" style="height: 500px; overflow-y: auto; display: flex; flex-direction: column;">
                    <!-- Messages will be injected here via JS -->
                    <div class="text-center text-muted my-3 small" id="loadingMsg">
                        <i class="fa-solid fa-spinner fa-spin me-2"></i> Loading messages...
                    </div>
                </div>

                <!-- Chat Input Area -->
                <div class="card-footer bg-white p-3 border-top-0">
                    <form id="chatForm">
                        <div class="input-group">
                            <input type="text" id="chatInput" class="form-control form-control-lg rounded-pill bg-light border-0 px-4" placeholder="Type a message to your class..." autocomplete="off" required>
                            <button type="submit" class="btn btn-primary rounded-circle ms-2 shadow-sm d-flex justify-content-center align-items-center" style="width: 50px; height: 50px;" id="sendBtn">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Chat Bubbles Style */
.msg-container {
    display: flex;
    margin-bottom: 15px;
    align-items: flex-end;
}
.msg-mine {
    justify-content: flex-end;
}
.msg-theirs {
    justify-content: flex-start;
}
.msg-bubble {
    max-width: 75%;
    padding: 10px 15px;
    border-radius: 20px;
    position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.msg-mine .msg-bubble {
    background-color: #007bff;
    color: white;
    border-bottom-right-radius: 4px;
}
.msg-theirs .msg-bubble {
    background-color: white;
    color: #333;
    border-bottom-left-radius: 4px;
}
.msg-meta {
    font-size: 0.7rem;
    margin-top: 5px;
    opacity: 0.8;
}
.msg-mine .msg-meta {
    text-align: right;
}
.sender-name {
    font-size: 0.75rem;
    font-weight: bold;
    color: #555;
    margin-bottom: 3px;
    display: block;
}
.msg-mine .sender-name {
    display: none; /* Don't show own name */
}
.avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
}
.msg-theirs .avatar {
    margin-right: 10px;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const chatBox = document.getElementById('chatBox');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    
    let lastMessageId = 0;
    let isFetching = false;
    
    function scrollToBottom() {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
    
    function appendMessage(msg) {
        document.getElementById('loadingMsg').style.display = 'none';
        
        const container = document.createElement('div');
        container.className = 'msg-container ' + (msg.is_mine ? 'msg-mine' : 'msg-theirs');
        
        let html = '';
        if (!msg.is_mine) {
            html += `<img src="${msg.pic}" class="avatar" alt="${msg.name}">`;
        }
        
        html += `<div class="msg-bubble">`;
        if (!msg.is_mine) {
            html += `<span class="sender-name">${msg.name}</span>`;
        }
        html += `<div>${msg.text}</div>
                 <div class="msg-meta">${msg.time}</div>
                 </div>`;
                 
        container.innerHTML = html;
        chatBox.appendChild(container);
        
        if (msg.id > lastMessageId) {
            lastMessageId = msg.id;
        }
    }

    function fetchMessages() {
        if (isFetching) return;
        isFetching = true;
        
        fetch('api/student_chat_api.php?action=fetch&last_id=' + lastMessageId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                const wasAtBottom = (chatBox.scrollTop + chatBox.clientHeight) >= (chatBox.scrollHeight - 50);
                
                data.messages.forEach(msg => {
                    appendMessage(msg);
                });
                
                // Auto scroll if user was at the bottom, or if it's the first load
                if (wasAtBottom || lastMessageId === data.messages[data.messages.length - 1].id) {
                    scrollToBottom();
                }
            } else if (data.success && lastMessageId === 0) {
                document.getElementById('loadingMsg').innerHTML = 'No messages yet. Say hi!';
            }
        })
        .catch(err => console.error(err))
        .finally(() => {
            isFetching = false;
        });
    }

    // Initial fetch
    fetchMessages();
    
    // Poll every 3 seconds
    setInterval(fetchMessages, 3000);

    // Send Message
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const text = chatInput.value.trim();
        if (!text) return;
        
        chatInput.disabled = true;
        sendBtn.disabled = true;
        
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('message', text);
        
        fetch('api/student_chat_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                chatInput.value = '';
                fetchMessages(); // immediately fetch the new message
            } else {
                alert(data.error || 'Failed to send message.');
            }
        })
        .catch(err => alert("Connection error"))
        .finally(() => {
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
