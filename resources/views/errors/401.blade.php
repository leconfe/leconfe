@extends('errors::minimal')

@php
    $code = '401';
    $title = 'Unauthorized';
    $message = __('general.error_401_message');
@endphp
