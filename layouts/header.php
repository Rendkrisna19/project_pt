<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kebun Sei Rokan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <link rel="icon" sizes="32x32" href="../assets/images/logo.png">
<link rel="icon" sizes="16x16" href="../assets/images/logo.png">
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/logo.png">

  <script src="/unpkg.com/alpinejs" defer></script>
<!-- Alpine.js (untuk dropdown) -->
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<!-- Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>



    <script>
        // Kustomisasi default font Tailwind
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-gray-50 font-poppins">
    <div class="flex min-h-screen">
        <?php include_once 'sidebar.php'; ?>
        
        <main class="flex-1 p-6 md:p-8">