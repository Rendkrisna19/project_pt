<?php
session_start();

// Cek Sesi
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../auth/login.php");
    exit;
}

$pageTitle = 'Group Chat';
$currentPage = 'chat';
include_once '../layouts/header.php';
?>

<style>
    :root { 
        --chat-primary: #0ea5e9; 
        --chat-primary-hover: #0284c7;
        --chat-bg: #f8fafc;
        --chat-bubble-me: #0ea5e9;
        --chat-bubble-other: #ffffff;
    }
    .chat-container { 
        height: calc(100vh - 120px); 
        display: flex; 
        flex-direction: column; 
        background: white; 
        border-radius: 16px; 
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); 
        overflow: hidden; 
        border: 1px solid #e2e8f0;
    }
    .chat-header {
        padding: 16px 24px;
        background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        color: white;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .chat-header-icon {
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }
    .chat-messages { 
        flex: 1; 
        overflow-y: auto; 
        padding: 24px; 
        background: var(--chat-bg); 
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    /* Scrollbar */
    .chat-messages::-webkit-scrollbar { width: 6px; }
    .chat-messages::-webkit-scrollbar-track { background: transparent; }
    .chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    
    .message-row { 
        display: flex; 
        width: 100%;
    }
    .message-row.me { justify-content: flex-end; }
    .message-row.other { justify-content: flex-start; }
    
    .message-content {
        max-width: 75%;
        display: flex;
        flex-direction: column;
    }
    .message-row.me .message-content { align-items: flex-end; }
    .message-row.other .message-content { align-items: flex-start; }
    
    .sender-info {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }
    .sender-name { font-size: 0.75rem; font-weight: 700; color: #64748b; }
    .sender-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; background: #e2e8f0; }
    
    .message-bubble { 
        padding: 12px 16px; 
        border-radius: 16px; 
        font-size: 0.95rem; 
        position: relative; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        line-height: 1.5;
        word-break: break-word;
    }
    
    .message-row.me .message-bubble { 
        background: var(--chat-bubble-me); 
        color: white; 
        border-bottom-right-radius: 4px; 
    }
    
    .message-row.other .message-bubble { 
        background: var(--chat-bubble-other); 
        color: #334155; 
        border: 1px solid #e2e8f0; 
        border-bottom-left-radius: 4px; 
    }
    
    .message-time { 
        font-size: 0.65rem; 
        margin-top: 6px; 
        display: block;
    }
    .message-row.me .message-time { color: #bae6fd; text-align: right; }
    .message-row.other .message-time { color: #94a3b8; }
    
    .chat-input-area { 
        padding: 16px 24px; 
        background: white; 
        border-top: 1px solid #e2e8f0; 
        display: flex; 
        gap: 12px; 
        align-items: flex-end; 
    }
    
    .input-wrapper {
        flex: 1;
        background: #f1f5f9;
        border-radius: 24px;
        padding: 4px;
        display: flex;
        align-items: center;
        border: 1px solid transparent;
        transition: all 0.3s;
    }
    .input-wrapper:focus-within {
        background: white;
        border-color: var(--chat-primary);
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }
    
    .chat-input { 
        flex: 1; 
        padding: 10px 16px; 
        background: transparent;
        border: none; 
        outline: none; 
        resize: none;
        max-height: 120px;
        font-size: 0.95rem;
        color: #334155;
    }
    
    .btn-action { 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border: none; 
        cursor: pointer; 
        transition: all 0.2s; 
    }
    
    .btn-attach {
        background: transparent;
        color: #64748b;
        position: relative;
    }
    .btn-attach:hover { background: #e2e8f0; color: var(--chat-primary); }
    .btn-attach input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    
    .btn-send { 
        background: var(--chat-primary); 
        color: white; 
        box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.3);
    }
    .btn-send:hover { 
        background: var(--chat-primary-hover); 
        transform: translateY(-2px); 
        box-shadow: 0 6px 8px -1px rgba(14, 165, 233, 0.4);
    }
    
    .file-preview { 
        display: none; 
        padding: 12px 24px; 
        background: #f8fafc; 
        border-top: 1px solid #e2e8f0; 
        align-items: center; 
        justify-content: space-between; 
    }
    .file-preview.active { display: flex; }
    .file-preview-info { display: flex; align-items: center; gap: 12px; }
    .file-preview-icon { width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
    .file-remove { color: #ef4444; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s; }
    .file-remove:hover { background: #fee2e2; }

    .chat-image {
        max-width: 250px;
        border-radius: 12px;
        cursor: pointer;
        transition: transform 0.2s;
        border: 2px solid rgba(255,255,255,0.2);
    }
    .chat-image:hover { transform: scale(1.02); }
    .chat-doc {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0,0,0,0.05);
        padding: 10px 16px;
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
        margin-top: 5px;
    }
    .message-row.me .chat-doc { background: rgba(255,255,255,0.1); color: white; }
    .message-row.me .chat-doc:hover { background: rgba(255,255,255,0.2); }
    .message-row.other .chat-doc { background: #f1f5f9; color: #0f172a; }
    .message-row.other .chat-doc:hover { background: #e2e8f0; }

    /* Modal Image Fullscreen */
    #imageModal {
        display: none;
        position: fixed;
        z-index: 9999;
        top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.9);
        backdrop-filter: blur(5px);
        justify-content: center;
        align-items: center;
    }
    #imageModal img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
    #imageModal .close-modal { position: absolute; top: 20px; right: 30px; color: white; font-size: 30px; cursor: pointer; transition: color 0.2s; }
    #imageModal .close-modal:hover { color: #f87171; }
</style>

<div class="px-4 md:px-8 mt-6 relative z-0">
    <div class="chat-container">
        
        <!-- Header -->
        <div class="chat-header">
            <div class="chat-header-icon">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div>
                <h2 class="font-bold text-lg leading-tight flex items-center gap-2">
                    Group Chat Internal
                    <span class="text-[10px] bg-green-500 text-white px-2 py-0.5 rounded-full flex items-center gap-1 font-medium shadow-sm border border-green-400">
                        <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span> 
                        <span id="online-count">1</span> Online
                    </span>
                </h2>
                <p class="text-xs text-sky-100 opacity-90">Komunikasi antar karyawan PTPN</p>
            </div>
        </div>

        <!-- Messages Area -->
        <div class="chat-messages" id="chat-messages">
            <!-- Loading indicator -->
            <div class="text-center text-slate-400 text-sm py-4" id="loading-msg">
                <i data-lucide="loader-2" class="w-5 h-5 animate-spin mx-auto mb-2"></i>
                Memuat pesan...
            </div>
        </div>

        <!-- File Preview Area -->
        <div class="file-preview" id="file-preview">
            <div class="file-preview-info">
                <div class="file-preview-icon">
                    <i data-lucide="file" class="w-5 h-5" id="preview-icon"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-slate-700" id="preview-name">filename.jpg</div>
                    <div class="text-xs text-slate-500" id="preview-size">1.2 MB</div>
                </div>
            </div>
            <div class="file-remove" onclick="removeFile()">
                <i data-lucide="x" class="w-5 h-5"></i>
            </div>
        </div>

        <!-- Input Area -->
        <div class="chat-input-area">
            <div class="input-wrapper">
                <button class="btn-action btn-attach" type="button" title="Lampirkan File/Foto">
                    <i data-lucide="paperclip" class="w-5 h-5"></i>
                    <input type="file" id="chat-file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                </button>
                <textarea id="chat-input" class="chat-input custom-scrollbar" placeholder="Ketik pesan Anda di sini..." rows="1" oninput="autoResize(this)"></textarea>
            </div>
            <button class="btn-action btn-send" onclick="sendMessage()" title="Kirim Pesan">
                <i data-lucide="send" class="w-5 h-5 ml-1"></i>
            </button>
        </div>
        
    </div>
</div>

<!-- Modal untuk View Image -->
<div id="imageModal" onclick="closeImageModal()">
    <span class="close-modal">&times;</span>
    <img id="modalImg" src="">
</div>

<script>
    let lastMsgId = 0;
    const msgContainer = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const fileInput = document.getElementById('chat-file');
    const filePreview = document.getElementById('file-preview');
    let isFetching = false;

    // Auto resize textarea
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight < 120 ? el.scrollHeight : 120) + 'px';
    }

    // Handle File Selection
    fileInput.addEventListener('change', function() {
        if(this.files && this.files[0]) {
            const file = this.files[0];
            document.getElementById('preview-name').textContent = file.name;
            document.getElementById('preview-size').textContent = (file.size / (1024*1024)).toFixed(2) + ' MB';
            
            const isImage = file.type.startsWith('image/');
            document.getElementById('preview-icon').setAttribute('data-lucide', isImage ? 'image' : 'file-text');
            lucide.createIcons();
            
            filePreview.classList.add('active');
            chatInput.focus();
        }
    });

    function removeFile() {
        fileInput.value = '';
        filePreview.classList.remove('active');
    }

    function openImage(src) {
        document.getElementById('modalImg').src = src;
        document.getElementById('imageModal').style.display = 'flex';
    }

    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
    }

    // Enter to send
    chatInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function scrollToBottom() {
        msgContainer.scrollTop = msgContainer.scrollHeight;
    }

    function renderMessage(msg) {
        const isMe = msg.is_me;
        const alignClass = isMe ? 'me' : 'other';
        const avatarUrl = msg.foto ? msg.foto : `https://ui-avatars.com/api/?name=${encodeURIComponent(msg.sender)}&background=random`;
        
        let mediaHtml = '';
        if (msg.file_url) {
            if (msg.file_type === 'image') {
                mediaHtml = `<img src="${msg.file_url}" class="chat-image mb-2" onclick="openImage('${msg.file_url}')" alt="Attachment">`;
            } else {
                mediaHtml = `
                    <a href="${msg.file_url}" target="_blank" class="chat-doc mb-2">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                        <span class="text-sm font-medium">Lihat Dokumen</span>
                    </a>
                `;
            }
        }

        const msgTextHtml = msg.message ? `<div>${msg.message.replace(/\\n/g, '<br>')}</div>` : '';

        const roleBadge = msg.role ? `<span class="text-[9px] px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 font-bold tracking-wider">${msg.role}</span>` : '';
        const flexReverse = isMe ? 'flex-row-reverse' : '';
        
        const senderInfo = `
            <div class="sender-info ${flexReverse}">
                <img src="${avatarUrl}" class="sender-avatar" alt="User">
                <span class="sender-name flex items-center gap-1">${isMe ? roleBadge + ' ' + msg.sender : msg.sender + ' ' + roleBadge}</span>
            </div>
        `;

        const html = `
            <div class="message-row ${alignClass}">
                <div class="message-content">
                    ${senderInfo}
                    <div class="message-bubble">
                        ${mediaHtml}
                        ${msgTextHtml}
                        <span class="message-time">${msg.time}</span>
                    </div>
                </div>
            </div>
        `;
        return html;
    }

    function fetchMessages() {
        if (isFetching) return;
        isFetching = true;

        const formData = new FormData();
        formData.append('action', 'fetch');

        fetch('chat_action.php?action=fetch&last_id=' + lastMsgId)
            .then(res => res.json())
            .then(data => {
                const loader = document.getElementById('loading-msg');
                if (loader) loader.remove();

                if (data.success) {
                    if (data.online_count !== undefined) {
                        document.getElementById('online-count').innerText = data.online_count;
                    }
                    
                    if (data.messages.length > 0) {
                        let html = '';
                        data.messages.forEach(msg => {
                            html += renderMessage(msg);
                            lastMsgId = msg.id;
                        });
                    
                        // Check if scrolled to bottom before appending
                        const isAtBottom = msgContainer.scrollHeight - msgContainer.scrollTop <= msgContainer.clientHeight + 50;
                        
                        msgContainer.insertAdjacentHTML('beforeend', html);
                        lucide.createIcons();
                        
                        if (isAtBottom || lastMsgId <= data.messages[data.messages.length-1].id) {
                            scrollToBottom();
                        }
                    }
                }
            })
            .catch(err => {
                console.error("Fetch error:", err);
            })
            .finally(() => {
                isFetching = false;
            });
    }

    function sendMessage() {
        const message = chatInput.value.trim();
        const file = fileInput.files[0];

        if (!message && !file) return;

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('message', message);
        if (file) formData.append('file', file);

        // UI Optimistic update (optional, but let's wait for server to ensure file upload is done)
        const btnSend = document.querySelector('.btn-send');
        btnSend.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i>';
        btnSend.disabled = true;

        fetch('chat_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                chatInput.value = '';
                chatInput.style.height = 'auto';
                removeFile();
                fetchMessages(); // Fetch immediately
                setTimeout(scrollToBottom, 200);
            } else {
                alert(data.message || 'Gagal mengirim pesan');
            }
        })
        .finally(() => {
            btnSend.innerHTML = '<i data-lucide="send" class="w-5 h-5 ml-1"></i>';
            btnSend.disabled = false;
            lucide.createIcons();
        });
    }

    // Initial fetch
    fetchMessages();

    // Polling every 3 seconds
    setInterval(fetchMessages, 3000);

</script>

<?php include_once '../layouts/footer.php'; ?>
