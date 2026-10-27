@extends('errors::minimal')

@php
    $code = '403';
    $title = 'Forbidden';
    $message = $exception->getMessage() ?: __('general.error_403_message');
@endphp
