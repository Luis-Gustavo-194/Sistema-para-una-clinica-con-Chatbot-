<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $titulo ? $titulo . ' · ' : '' }}{{ config('clinica.nombre') }}</title>
<link rel="icon" href="{{ asset('img/logo.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
