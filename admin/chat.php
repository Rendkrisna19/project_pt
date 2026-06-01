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
    .chat-container {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 120px);
        background-color: #efeae2; /* WhatsApp Web bg color */
        border-radius: 0;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        position: relative;
    }
    
    /* WhatsApp Pattern Overlay (Subtle) */
    .chat-container::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        opacity: 0.4;
        background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
        background-size: 20px 20px;
        z-index: 0;
        pointer-events: none;
    }

    .chat-header {
        padding: 12px 16px;
        color: white;
        display: flex;
        align-items: center;
        gap: 12px;
        position: sticky;
        top: 0;
        z-index: 10;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .chat-header-icon {
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        scroll-behavior: smooth;
        position: relative;
        z-index: 1;
    }
    .message-row {
        display: flex;
        width: 100%;
        margin-bottom: 4px;
    }
    .message-row.me {
        justify-content: flex-end;
    }
    .message-content {
        max-width: 65%;
        display: flex;
        flex-direction: column;
    }
    .message-row.me .message-content {
        align-items: flex-end;
    }
    
    .message-bubble {
        padding: 6px 8px 8px 10px;
        border-radius: 8px;
        position: relative;
        font-size: 14.5px;
        line-height: 1.4;
        color: #111b21;
        box-shadow: 0 1px 0.5px rgba(11,20,26,.13);
    }
    .message-row.other .message-bubble {
        background: #ffffff;
        border-top-left-radius: 0;
    }
    .message-row.me .message-bubble {
        background: #dcf8c6; /* WA Web light green */
        border-top-right-radius: 0;
    }
    
    /* WhatsApp Tails */
    .message-row.other .message-bubble::before {
        content: "";
        position: absolute;
        top: 0;
        left: -8px;
        width: 8px;
        height: 12px;
        background: linear-gradient(to bottom right, transparent 50%, #ffffff 50%);
    }
    .message-row.me .message-bubble::before {
        content: "";
        position: absolute;
        top: 0;
        right: -8px;
        width: 8px;
        height: 12px;
        background: linear-gradient(to bottom left, transparent 50%, #dcf8c6 50%);
    }

    .sender-info {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 2px;
        padding: 0 4px;
    }
    .sender-name {
        font-size: 12px;
        font-weight: 600;
        color: #0284c7; /* Cyan emphasis for sender names */
    }
    .sender-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }
    .message-time {
        font-size: 11px;
        color: #667781;
        float: right;
        margin-left: 12px;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 2px;
    }
    
    .chat-input-area {
        padding: 12px 16px;
        background: #f0f2f5;
        border-top: 1px solid #d1d5db;
        display: flex;
        gap: 12px;
        align-items: flex-end;
        position: relative;
        z-index: 10;
    }
    .input-wrapper {
        flex: 1;
        background: #ffffff;
        border-radius: 24px;
        display: flex;
        align-items: flex-end;
        padding: 6px 12px;
        min-height: 44px;
    }
    .chat-input {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        padding: 8px 4px;
        resize: none;
        max-height: 120px;
        font-size: 15px;
        color: #111b21;
    }
    .chat-input::placeholder {
        color: #8696a0;
    }
    .btn-action {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        color: #54656f;
    }
    .btn-action:hover {
        background: rgba(11,20,26,.05);
    }
    .btn-send {
        background: #00a884; /* WA send button color, or cyan */
        color: white;
        flex-shrink: 0;
    }
    .btn-send:hover {
        background: #008f6f;
    }
    
    /* Media styling */
    .chat-image { border-radius: 6px; cursor: pointer; max-width: 100%; max-height: 250px; object-fit: contain; }
    
    /* Document Link Styling */
    .chat-doc {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: rgba(0,0,0,0.05);
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
    }
    .chat-doc:hover {
        background: rgba(0,0,0,0.1);
    }
    
    /* Dropdown Menu (WA Style) */
    .msg-dropdown-btn {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(255,255,255,0.8);
        color: #54656f;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .message-bubble:hover .msg-dropdown-btn {
        display: flex;
    }
    /* Always show on mobile */
    @media (max-width: 768px) {
        .msg-dropdown-btn { display: flex; opacity: 0.6; background: transparent; box-shadow: none; }
    }
    
    .msg-dropdown-menu {
        position: absolute;
        top: 25px;
        right: 0;
        background: white;
        border-radius: 4px;
        box-shadow: 0 2px 5px 0 rgba(11,20,26,.26),0 2px 10px 0 rgba(11,20,26,.16);
        z-index: 50;
        width: 120px;
        overflow: hidden;
        display: none;
    }
    .msg-dropdown-menu.show { display: block; }
    .msg-dropdown-item {
        padding: 10px 14px;
        font-size: 14px;
        color: #3b4a54;
        cursor: pointer;
        transition: background 0.1s;
    }
    .msg-dropdown-item:hover { background: #f5f6f6; }
    
    #reply-preview {
        background: #f0f2f5;
        border-left-color: #00a884;
    }

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
    
    /* File Preview (Upload Area) */
    .file-preview { 
        display: none; 
        padding: 12px 24px; 
        background: #f8fafc; 
        border-top: 1px solid #e2e8f0; 
        align-items: center; 
        justify-content: space-between; 
        z-index: 10;
        position: relative;
    }
    .file-preview.active { display: flex; }
    .file-preview-info { display: flex; align-items: center; gap: 12px; }
    .file-preview-icon { width: 36px; height: 36px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
    .file-remove { color: #ef4444; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s; }
    .file-remove:hover { background: #fee2e2; }

    /* Skeleton Loader */
    .skeleton-bubble {
        width: 250px;
        height: 60px;
        background: #e2e8f0;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
    }
    .skeleton-bubble::after {
        content: "";
        position: absolute;
        top: 0; left: -100%; width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: loading-pulse 1.5s infinite;
    }
    .skeleton-avatar {
        width: 24px; height: 24px; border-radius: 50%; background: #e2e8f0;
        position: relative; overflow: hidden;
    }
    .skeleton-avatar::after {
        content: ""; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: loading-pulse 1.5s infinite;
    }
    @keyframes loading-pulse {
        0% { left: -100%; }
        100% { left: 200%; }
    }
</style>

<div class="px-0 md:px-0 mt-0 relative z-0">
    <div class="chat-container">
        
        <!-- Header -->
        <div class="chat-header bg-sky-500 shadow">
            <div class="chat-header-icon">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div class="flex-1">
                <h2 class="font-bold text-lg leading-tight flex items-center gap-2">
                    Forum Diskusi
                </h2>
            </div>
            <!-- Online badge on the right -->
            <div class="text-[10px] bg-white/20 text-white px-2 py-1 rounded-full flex items-center gap-1 font-medium shadow-sm border border-white/30">
                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span> 
                <span id="online-count">1</span> Online
            </div>
        </div>

        <!-- Messages Area -->
        <div class="chat-messages" id="chat-messages">
            <!-- Loading indicator -->
            <div id="loading-msg" class="w-full">
                <div class="message-row other mb-4">
                    <div class="message-content">
                        <div class="sender-info">
                            <div class="skeleton-avatar"></div>
                            <div class="skeleton-avatar" style="width: 80px; height: 12px; border-radius: 4px;"></div>
                        </div>
                        <div class="skeleton-bubble"></div>
                    </div>
                </div>
                <div class="message-row me mb-4">
                    <div class="message-content">
                        <div class="sender-info flex-row-reverse">
                            <div class="skeleton-avatar"></div>
                            <div class="skeleton-avatar" style="width: 100px; height: 12px; border-radius: 4px;"></div>
                        </div>
                        <div class="skeleton-bubble"></div>
                    </div>
                </div>
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

        <!-- Reply Preview Area -->
        <div id="reply-preview" class="hidden bg-slate-50 px-4 py-2 border-t border-slate-200 border-l-4 border-l-emerald-500 flex justify-between items-center relative z-10 shadow-sm">
            <div class="flex-1 overflow-hidden">
                <div class="text-xs font-bold text-emerald-600 mb-0.5" id="reply-preview-sender">Sender</div>
                <div class="text-xs text-slate-600 truncate" id="reply-preview-text">Pesan yang dibalas...</div>
            </div>
            <button onclick="cancelReply()" class="text-slate-400 hover:text-red-500 p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Input Area -->
        <div class="chat-input-area">
            <div class="input-wrapper">
                <button class="btn-action btn-attach" type="button" title="Lampirkan File/Foto/Video/Audio" onclick="document.getElementById('chat-file').click()">
                    <i data-lucide="paperclip" class="w-5 h-5"></i>
                    <input type="file" id="chat-file" class="hidden" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx">
                </button>
                <button class="btn-action btn-attach md:hidden" type="button" title="Ambil Foto" onclick="document.getElementById('chat-camera').click()">
                    <i data-lucide="camera" class="w-5 h-5"></i>
                    <input type="file" id="chat-camera" class="hidden" accept="image/*" capture="environment">
                </button>
                
                <textarea id="chat-input" class="chat-input custom-scrollbar" placeholder="Ketik pesan Anda di sini..." rows="1" oninput="autoResize(this)"></textarea>
                
                <!-- Voice Recorder Button -->
                <button id="btn-record" class="btn-action btn-attach" type="button" title="Tahan untuk Rekam Suara" onmousedown="startRecording()" onmouseup="stopRecording()" ontouchstart="startRecording(event)" ontouchend="stopRecording(event)">
                    <i data-lucide="mic" class="w-5 h-5 text-slate-500"></i>
                </button>
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
    const currentUserRole = '<?= $_SESSION["role"] ?? "" ?>';
    let lastMsgId = 0;
    let firstMsgId = 0;
    let hasMoreMessages = true;
    let isFetchingOld = false;
    
    const msgContainer = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const fileInput = document.getElementById('chat-file');
    const filePreview = document.getElementById('file-preview');
    let isFetching = false;
    
    // Reply State
    let replyToId = null;

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
            } else if (msg.file_type === 'audio') {
                mediaHtml = `<audio controls src="${msg.file_url}" class="mb-2 max-w-full h-10 rounded"></audio>`;
            } else if (msg.file_type === 'video') {
                mediaHtml = `<video controls src="${msg.file_url}" class="mb-2 max-w-full rounded" style="max-height: 200px;"></video>`;
            } else {
                mediaHtml = `
                    <a href="${msg.file_url}" target="_blank" class="chat-doc mb-2">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                        <span class="text-sm font-medium">Lihat File</span>
                    </a>
                `;
            }
        }

        // Reply Quoted Box
        let replyHtml = '';
        if (msg.reply_to_sender) {
            replyHtml = `
                <div class="bg-black/5 border-l-4 border-l-emerald-500 rounded p-2 mb-2 text-xs opacity-80 cursor-pointer">
                    <div class="font-bold text-emerald-600 mb-0.5">${msg.reply_to_sender}</div>
                    <div class="truncate">${msg.reply_to_message || 'Lampiran file'}</div>
                </div>
            `;
        }

        let msgTextHtml = msg.message ? `<div>${msg.message.replace(/\\n/g, '<br>')}</div>` : '';
        if (msg.is_edited) msgTextHtml += '<span class="text-[9px] text-slate-400 italic mt-1 block">Telah diedit</span>';

        const isDeleted = msg.is_deleted;
        if (isDeleted) {
            msgTextHtml = '<div class="italic text-slate-400 flex items-center gap-1"><i data-lucide="ban" class="w-3 h-3"></i> Pesan dihapus oleh Admin.</div>';
            mediaHtml = ''; 
            replyHtml = ''; 
        }

        const roleBadge = msg.role ? `<span class="text-[9px] px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 font-bold tracking-wider">${msg.role}</span>` : '';
        const flexReverse = isMe ? 'flex-row-reverse' : '';
        
        // Avatar is clickable
        const senderInfo = `
            <div class="sender-info ${flexReverse}">
                <img src="${avatarUrl}" class="sender-avatar cursor-pointer" alt="User" onclick="showUserProfile(${msg.user_id}, '${msg.sender}', '${msg.role}', '${avatarUrl}')">
                <span class="sender-name flex items-center gap-1">${isMe ? roleBadge + ' ' + msg.sender : msg.sender + ' ' + roleBadge}</span>
            </div>
        `;

        // Text for reply/edit
        const textForReply = (msg.message || 'Lampiran').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        
        // WA Style Dropdown Menu
        let dropdownHtml = '';
        if (!isDeleted) {
            dropdownHtml = `
                <div class="msg-dropdown-btn" onclick="toggleDropdown(event, ${msg.id})">
                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                </div>
                <div class="msg-dropdown-menu" id="dropdown-${msg.id}">
                    <div class="msg-dropdown-item" onclick="initReply(${msg.id}, '${msg.sender}', '${textForReply}')">Balas</div>
            `;
            if (isMe) {
                dropdownHtml += `<div class="msg-dropdown-item" onclick="initEdit(${msg.id}, '${textForReply}')">Edit</div>`;
            }
            if (currentUserRole.toUpperCase() === 'ADMIN') {
                dropdownHtml += `<div class="msg-dropdown-item text-red-600" onclick="deleteMessage(${msg.id})">Hapus</div>`;
            }
            dropdownHtml += `</div>`;
        }
        
        const html = `
            <div class="message-row ${alignClass}" id="msg-${msg.id}" ondblclick="initReply(${msg.id}, '${msg.sender}', '${textForReply}')">
                <div class="message-content">
                    ${!isMe ? senderInfo : ''}
                    <div class="message-bubble group">
                        ${dropdownHtml}
                        ${replyHtml}
                        ${mediaHtml}
                        ${msgTextHtml}
                        <div class="message-time">
                            ${msg.time}
                            ${isMe ? '<i data-lucide="check-check" class="w-3.5 h-3.5 text-sky-500 ml-1"></i>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        return html;
    }

    // Toggle dropdown
    function toggleDropdown(e, id) {
        e.stopPropagation();
        // Close all other dropdowns
        document.querySelectorAll('.msg-dropdown-menu').forEach(el => el.classList.remove('show'));
        // Open this one
        document.getElementById('dropdown-' + id).classList.add('show');
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.msg-dropdown-menu').forEach(el => el.classList.remove('show'));
    });

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
                    
                    if (data.messages && data.messages.length > 0) {
                        let html = '';
                        data.messages.forEach(msg => {
                            if (firstMsgId === 0) firstMsgId = msg.id; // Init first message
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

    // Infinite scroll for older messages
    msgContainer.addEventListener('scroll', function() {
        if (msgContainer.scrollTop === 0 && hasMoreMessages && !isFetchingOld && firstMsgId > 0) {
            fetchOldMessages();
        }
    });

    function fetchOldMessages() {
        isFetchingOld = true;
        
        // Add skeleton loader at the top
        const oldLoader = document.createElement('div');
        oldLoader.id = 'old-loading-msg';
        oldLoader.innerHTML = `
            <div class="message-row other mb-4 opacity-50">
                <div class="message-content">
                    <div class="skeleton-bubble" style="height:40px; width:150px;"></div>
                </div>
            </div>`;
        msgContainer.prepend(oldLoader);

        fetch('chat_action.php?action=fetch_old&first_id=' + firstMsgId)
            .then(res => res.json())
            .then(data => {
                oldLoader.remove();
                if (data.success && data.messages.length > 0) {
                    const previousScrollHeight = msgContainer.scrollHeight;
                    
                    let html = '';
                    data.messages.forEach(msg => {
                        html += renderMessage(msg);
                    });
                    
                    firstMsgId = data.messages[0].id; // Update firstMsgId
                    
                    msgContainer.insertAdjacentHTML('afterbegin', html);
                    lucide.createIcons();
                    
                    // Maintain scroll position
                    const newScrollHeight = msgContainer.scrollHeight;
                    msgContainer.scrollTop = newScrollHeight - previousScrollHeight;
                } else {
                    hasMoreMessages = false; // No more older messages
                }
            })
            .catch(err => {
                console.error("Fetch old error:", err);
                if(oldLoader) oldLoader.remove();
            })
            .finally(() => {
                isFetchingOld = false;
            });
    }

    function initReply(msgId, sender, text) {
        replyToId = msgId;
        document.getElementById('reply-preview-sender').textContent = sender;
        document.getElementById('reply-preview-text').textContent = text;
        document.getElementById('reply-preview').classList.remove('hidden');
        chatInput.focus();
    }

    function cancelReply() {
        replyToId = null;
        document.getElementById('reply-preview').classList.add('hidden');
    }

    let editMsgId = null;

    function initEdit(msgId, text) {
        editMsgId = msgId;
        chatInput.value = text.replace(/<br>/g, '\n');
        chatInput.focus();
        document.querySelector('.btn-send').innerHTML = '<i data-lucide="check" class="w-5 h-5 text-white"></i>';
        lucide.createIcons();
    }

    function deleteMessage(id) {
        Swal.fire({
            title: 'Hapus Pesan?',
            text: 'Tindakan ini tidak dapat dibatalkan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('msg_id', id);
                fetch('chat_action.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.updated_message) {
                        const row = document.getElementById('msg-' + id);
                        if (row) {
                            row.outerHTML = renderMessage(res.updated_message);
                            lucide.createIcons();
                        }
                        Swal.fire('Terhapus!', 'Pesan berhasil ditarik.', 'success');
                    } else {
                        Swal.fire('Gagal', res.message || 'Gagal memproses', 'error');
                    }
                });
            }
        });
    }

    function sendMessage() {
        const message = chatInput.value.trim();
        const file = fileInput.files[0];

        if (!message && !file && !editMsgId) return;

        const formData = new FormData();
        
        if (editMsgId) {
            formData.append('action', 'edit');
            formData.append('msg_id', editMsgId);
            formData.append('message', message);
        } else {
            formData.append('action', 'send');
            formData.append('message', message);
            if (file) formData.append('file', file);
            if (replyToId) formData.append('reply_to_id', replyToId);
        }

        // UI Optimistic update
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
                
                if (editMsgId && data.updated_message) {
                    const row = document.getElementById('msg-' + editMsgId);
                    if (row) {
                        row.outerHTML = renderMessage(data.updated_message);
                        lucide.createIcons();
                    }
                    editMsgId = null;
                } else {
                    removeFile();
                    cancelReply();
                    fetchMessages(); 
                    setTimeout(scrollToBottom, 200);
                }
            } else {
                alert(data.message || 'Gagal memproses pesan');
            }
        })
        .finally(() => {
            btnSend.innerHTML = '<i data-lucide="send" class="w-5 h-5 ml-1"></i>';
            btnSend.disabled = false;
            lucide.createIcons();
        });
    }

    function showUserProfile(userId, name, role, avatar) {
        Swal.fire({
            html: `
                <div class="flex flex-col items-center p-4">
                    <img src="${avatar}" class="w-24 h-24 rounded-full shadow-lg border-4 border-white mb-4">
                    <h3 class="text-xl font-bold text-slate-800">${name}</h3>
                    <span class="text-xs bg-sky-100 text-sky-700 px-2 py-1 rounded mt-1 font-bold tracking-widest">${role}</span>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            customClass: {
                popup: 'rounded-2xl'
            }
        });
    }

    // Audio Recorder logic
    let mediaRecorder;
    let audioChunks = [];
    let isRecording = false;

    async function startRecording(e) {
        if(e) e.preventDefault();
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = e => {
                if (e.data.size > 0) audioChunks.push(e.data);
            };

            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const audioFile = new File([audioBlob], "voice_note.webm", { type: 'audio/webm' });
                
                // Set file to input (using DataTransfer)
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(audioFile);
                document.getElementById('chat-file').files = dataTransfer.files;
                
                // Show preview
                document.getElementById('preview-icon').setAttribute('data-lucide', 'mic');
                document.getElementById('preview-name').innerText = 'Voice Note';
                document.getElementById('preview-size').innerText = (audioFile.size / 1024).toFixed(1) + ' KB';
                document.getElementById('file-preview').style.display = 'flex';
                lucide.createIcons();
            };

            mediaRecorder.start();
            isRecording = true;
            document.getElementById('btn-record').classList.add('text-red-500', 'animate-pulse');
            document.querySelector('#btn-record i').classList.replace('text-slate-500', 'text-red-500');
        } catch (err) {
            Swal.fire('Error', 'Izin mikrofon ditolak atau perangkat tidak mendukung.', 'error');
        }
    }

    function stopRecording(e) {
        if(e) e.preventDefault();
        if (isRecording && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            isRecording = false;
            document.getElementById('btn-record').classList.remove('text-red-500', 'animate-pulse');
            document.querySelector('#btn-record i').classList.replace('text-red-500', 'text-slate-500');
        }
    }

    // Handle camera input (if user selects a file from the hidden camera input)
    document.getElementById('chat-camera').addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            document.getElementById('chat-file').files = this.files;
            handleFileSelect({target: {files: this.files}});
        }
    });

    // Initial fetch
    fetchMessages();

    // Polling every 3 seconds
    setInterval(fetchMessages, 3000);

</script>

<?php include_once '../layouts/footer.php'; ?>
