<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>José Rafael Gutierrez - Matemático & Desarrollador Web Full Stack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 min-h-screen">
    @if(isset($showNotFound) && $showNotFound)
    <div class="bg-amber-500/10 border-l-4 border-amber-500 p-4 mb-0">
        <div class="max-w-4xl mx-auto flex items-center">
            <svg class="w-6 h-6 text-amber-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-amber-200">
                <strong>No ha sido posible encontrar el recurso solicitado.</strong>
                El enlace que buscas no existe o ha expirado.
            </p>
        </div>
    </div>
    @endif

    <div class="max-w-4xl mx-auto px-4 py-16">
        <header class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
                José Rafael Gutierrez
            </h1>
            <p class="text-xl text-slate-400">
                Matemático Puro · Consultor & Desarrollador Web Full Stack
            </p>
        </header>

        <div class="grid md:grid-cols-2 gap-8 mb-16">
            <!-- Tecnología y Desarrollo Web -->
            <a href="https://jose-gutierrez.com/" target="_blank" rel="noopener noreferrer"
               class="group block bg-slate-800/50 backdrop-blur border border-slate-700 rounded-2xl p-8 hover:border-blue-500/50 hover:bg-slate-800/80 transition-all duration-300">
                <div class="w-14 h-14 bg-blue-500/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-500/30 transition-colors">
                    <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-white mb-3">Tecnología y Desarrollo Web</h2>
                <p class="text-slate-400 mb-4">
                    Artículos, tutoriales y recursos sobre desarrollo web, programación y las últimas tecnologías.
                </p>
                <span class="inline-flex items-center text-blue-400 font-medium group-hover:text-blue-300">
                    Visitar jose-gutierrez.com
                    <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </span>
            </a>

            <!-- Apologética Cristiana -->
            <a href="https://bajolalupa.net/" target="_blank" rel="noopener noreferrer"
               class="group block bg-slate-800/50 backdrop-blur border border-slate-700 rounded-2xl p-8 hover:border-emerald-500/50 hover:bg-slate-800/80 transition-all duration-300">
                <div class="w-14 h-14 bg-emerald-500/20 rounded-xl flex items-center justify-center mb-6 group-hover:bg-emerald-500/30 transition-colors">
                    <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-white mb-3">Apologética Cristiana</h2>
                <p class="text-slate-400 mb-4">
                    Reflexiones, estudios y análisis desde una perspectiva cristiana. Fe y razón en armonía.
                </p>
                <span class="inline-flex items-center text-emerald-400 font-medium group-hover:text-emerald-300">
                    Visitar bajolalupa.net
                    <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </span>
            </a>
        </div>

        <!-- Consultoría -->
        <div class="bg-gradient-to-r from-violet-500/10 to-purple-500/10 border border-violet-500/20 rounded-2xl p-8 text-center">
            <div class="w-14 h-14 bg-violet-500/20 rounded-xl flex items-center justify-center mx-auto mb-6">
                <svg class="w-7 h-7 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-semibold text-white mb-3">Servicios de Consultoría</h2>
            <p class="text-slate-400 mb-6 max-w-2xl mx-auto">
                ¿Necesitas ayuda con tu proyecto de desarrollo web? Ofrezco servicios de consultoría,
                desarrollo a medida y asesoramiento técnico.
            </p>
            <a href="mailto:josefo727@gmail.com"
               class="inline-flex items-center px-6 py-3 bg-violet-600 hover:bg-violet-500 text-white font-medium rounded-xl transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                josefo727@gmail.com
            </a>
        </div>

        <footer class="mt-16 text-center text-slate-500 text-sm">
            <p>&copy; {{ date('Y') }} José Rafael Gutierrez. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>
