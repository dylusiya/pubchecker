<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        window.onload = function () {
            // Redirect ke app
            window.location.href = "id.go.bps://callback";
            
            // Fallback jika tidak ada app
            setTimeout(function () {
                window.location.href = "{{ url('/dashboard') }}";
            }, 2000);
        };
    </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="text-center bg-white rounded-2xl shadow-2xl p-12 max-w-md">
            <div class="mb-6">
                <div class="animate-spin rounded-full h-20 w-20 border-b-4 border-blue-600 mx-auto"></div>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Mengarahkan ke aplikasi...</h2>
            <p class="text-gray-600">Mohon tunggu sebentar</p>
            <div class="mt-8 text-sm text-gray-500">
                <p>Jika tidak otomatis redirect,</p>
                <a href="{{ url('/dashboard') }}" class="text-blue-600 hover:underline font-medium">klik di sini</a>
            </div>
        </div>
    </div>
</body>
</html>