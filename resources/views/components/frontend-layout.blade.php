<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Primary Meta Tags -->
        <meta name="title" content="TMEX - Tap. Go. Relax">
        <meta name="description" content="Skilled Drivers. Transparent Fares. Ride with confidence, comfort, and clarity. Our expert drivers and honest pricing deliver a premium experience every time you travel." />

        <!-- Open Graph / Facebook / WhatsApp / Teams -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="TMEX - Tap. Go. Relax">
        <meta property="og:description" content="Skilled Drivers. Transparent Fares. Ride with confidence, comfort, and clarity. Our expert drivers and honest pricing deliver a premium experience every time you travel." />
        <meta property="og:image" content="{{ asset('frontend-website/img/website/preview_img.png') }}">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="TMEX - Tap. Go. Relax">
        <meta property="twitter:description" content="Skilled Drivers. Transparent Fares. Ride with confidence, comfort, and clarity. Our expert drivers and honest pricing deliver a premium experience every time you travel." />
        <meta property="twitter:image" content="{{ asset('frontend-website/img/website/preview_img.png') }}">
        
        @include('frontend-partials._head')

    </head>
    <body class="" id="app">

        @include('frontend-partials._body')

    </body>

</html>