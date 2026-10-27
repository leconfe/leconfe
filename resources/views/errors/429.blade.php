@extends('errors::minimal')

@php
    $code = '429';
    $title = 'Too Many Request';
    $message = __('general.error_429_message');
@endphp

