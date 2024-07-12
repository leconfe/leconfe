@extends('errors::minimal')

@php
    $code = '403';
    $title = $exception->getMessage() ?: __('translation.errorsBlade.403Forbidden');
    $message = __('translation.errorsBlade.403Message');
@endphp
