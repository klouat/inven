<!DOCTYPE html>
<html lang="en" class="bg-neutral-50 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Fisch Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; color: #171717; }
        .panel { background-color: #ffffff; border: 1px solid #e5e5e5; }
        .btn { background-color: #171717; color: #ffffff; transition: background-color 0.2s; }
        .btn:hover { background-color: #404040; }
        input { border: 1px solid #d4d4d4; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #a3a3a3; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm panel p-8 rounded-sm shadow-sm">
        <div class="text-center mb-10">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900 mb-1">Create Account</h1>
            <p class="text-neutral-500 text-sm">Join the database deck</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 border border-red-200 bg-red-50 text-red-700 text-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('register') }}" method="POST" class="space-y-4">
            @csrf
            
            <div>
                <label for="name" class="block text-xs uppercase font-semibold text-neutral-500 mb-1.5 tracking-wider">Display Name</label>
                <div class="relative">
                    <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required class="w-full pl-9 pr-3 py-2 text-sm bg-neutral-50 focus:bg-white" placeholder="John Doe">
                </div>
            </div>

            <div>
                <label for="email" class="block text-xs uppercase font-semibold text-neutral-500 mb-1.5 tracking-wider">Email Address</label>
                <div class="relative">
                    <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required class="w-full pl-9 pr-3 py-2 text-sm bg-neutral-50 focus:bg-white" placeholder="you@example.com">
                </div>
            </div>

            <div>
                 <label for="password" class="block text-xs uppercase font-semibold text-neutral-500 mb-1.5 tracking-wider">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                    <input type="password" name="password" id="password" required class="w-full pl-9 pr-3 py-2 text-sm bg-neutral-50 focus:bg-white">
                </div>
            </div>
            
            <div>
                 <label for="password_confirmation" class="block text-xs uppercase font-semibold text-neutral-500 mb-1.5 tracking-wider">Confirm Password</label>
                <div class="relative">
                    <i data-lucide="check" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400"></i>
                    <input type="password" name="password_confirmation" id="password_confirmation" required class="w-full pl-9 pr-3 py-2 text-sm bg-neutral-50 focus:bg-white">
                </div>
            </div>

            <button type="submit" class="w-full btn py-2.5 text-sm font-medium mt-6 cursor-pointer">
                Register
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-neutral-500">
            Already registered? 
            <a href="{{ route('login') }}" class="text-black font-semibold hover:underline">Sign in</a>
        </p>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
