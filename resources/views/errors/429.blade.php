@extends('errors.layout')

@section('code', '429')
@section('title', 'Rate limit exceeded')
@section('message', 'Too many requests in a short period. Throttling is active. Please wait before retrying.')
