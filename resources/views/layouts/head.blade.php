<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="description" content="CentralCloud, hébergement managé et sécurisé pour CentralPanel."><title>{{ isset($title) ? $title.' · CentralCloud' : 'CentralCloud' }}</title>
<script>window.applyTheme=function(t){const d=t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('dark',d)};window.applyTheme(localStorage.getItem('theme')||'system')</script>
@vite(['resources/css/app.css','resources/js/app.js'])
