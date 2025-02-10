@extends('errors::minimal')

@php
    $code = '403';
    $title = 'Forbidden';
    $message = $exception->getMessage() ?: 'Access denied, you do not have permission to access this page';
@endphp
