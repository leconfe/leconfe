@extends('errors::minimal')

@php
    $code = '500';
    $title = 'Server Error';
    $message = __('general.error_500_message');
@endphp
