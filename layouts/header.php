<?php
// layouts/header.php
// Topbar + wrapper konten, selaras dengan sidebar baru
$currentPage = $currentPage ?? 'dashboard';
$userName    = $_SESSION['user_nama'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="id" x-data="{ sidebarOpen:false }"
      x-init="$watch('sidebarOpen', v => { document.body.style.overflow = v ? 'hidden' : '' })">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard - Kebun Sei Rokan' ?></title>

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Optional libs -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <link rel="icon" sizes="32x32" href="../assets/images/logo.png">
  <link rel="icon" sizes="16x16" href="../assets/images/logo.png">
  <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/logo.png">

  <style>
    .scrollbar-hide { scrollbar-width: none; -ms-overflow-style: none; }
    .scrollbar-hide::-webkit-scrollbar { width: 0; height: 0; display: none; }
    html { scroll-behavior: smooth; }
  </style>
  <script>
    tailwind.config = {
      theme: {
        extend: { fontFamily: { poppins: ['Poppins','sans-serif'] } }
      }
    }
  </script>
</head>
<body class="bg-gray-50 font-poppins">
  <?php include_once __DIR__ . '/sidebar.php'; ?>

  <!-- TOPBAR (sticky, 56px) -->
  <header class="fixed top-0 inset-x-0 z-30 bg-white border-b md:ml-72">
    <div class="flex items-center h-14 px-4 gap-3">
      <!-- Hamburger (mobile) -->
      <button type="button"
              class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-700 hover:bg-gray-100"
              @click="sidebarOpen = true" aria-label="Buka menu">
        <i data-lucide="menu" class="w-6 h-6"></i>
      </button>

      <!-- Title area (opsional; bisa diubah per-halaman) -->
      <div class="flex items-center gap-2">
        <span class="text-base font-semibold text-gray-800">
          <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : ucfirst($currentPage) ?>
        </span>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <!-- contoh tombol topbar (optional)
        <button class="p-2 rounded-lg hover:bg-gray-100"><i data-lucide="bell" class="w-5 h-5"></i></button>
        -->
      </div>
    </div>
  </header>

  <!-- WRAPPER KONTEN
       - pt-14: offset tinggi header sticky (14*4=56px)
       - md:ml-72: offset sidebar desktop
  -->
  <div class="md:ml-72 pt-14 min-h-screen">
    <main class="p-6 md:p-8">

     <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
      }
    });
  </script>