@extends('errors::minimal')

@php
    $code = '500';
    $title = __('translation.errorsBlade.500ServerError');
    $message = __('translation.errorsBlade.500Message');
@endphp
