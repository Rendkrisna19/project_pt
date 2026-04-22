<?php
// File ini adalah gerbang utama aplikasi.
// Langsung arahkan (redirect) pengguna ke halaman login tanpa ekstensi .php

header("Location: auth/login");
exit();
?>