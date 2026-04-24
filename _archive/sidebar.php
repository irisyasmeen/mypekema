<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind CSS is included in the main file, so it's not needed here -->
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Additional styles for the sidebar if needed */
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-800 text-white min-h-screen p-4">
        <div class="mb-10 text-center">
            <i class="fas fa-warehouse text-3xl text-blue-400"></i>
            <h1 class="text-2xl font-bold mt-2">MyPEKEMA</h1>
            <p class="text-sm text-gray-400">PEKEMA Management</p>
        </div>
        <nav>
            <ul>
                <li class="mb-4">
                    <a href="index.php"
                        class="flex items-center p-3 rounded-lg bg-gray-700 text-white hover:bg-gray-600 transition-colors">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-4 font-semibold">Dashboard</span>
                    </a>
                </li>
                <li class="mb-4">
                    <a href="#"
                        class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                        <i class="fas fa-boxes-stacked w-6 text-center"></i>
                        <span class="ml-4 font-semibold">Inventory</span>
                    </a>
                </li>
                <li class="mb-4">
                    <a href="#"
                        class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                        <i class="fas fa-file-alt w-6 text-center"></i>
                        <span class="ml-4 font-semibold">Reports</span>
                    </a>
                </li>
                <li class="mb-4">
                    <a href="#"
                        class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span class="ml-4 font-semibold">Users</span>
                    </a>
                </li>
                <li class="mb-4">
                    <a href="#"
                        class="flex items-center p-3 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-4 font-semibold">Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
</body>

</html>