@extends('layout.layout')
@section('content')
    <form method="POST" enctype="multipart/form-data" action="{{route('dashboard.store')}}">
    <div class="row mt-5">
        <div class="col-12">
            <h4>Upload File</h4>
        </div>
    </div>
    <div class="row">
        <div class="mb-3">
            <label for="formFile" class="form-label">Upload File CSV</label>
            <input class="form-control" type="file" name="file" id="formFile">
        </div>
    </div>
    <div class="row">
        <div class="col-auto">
            <button type="submit" class="btn btn-primary mb-3">Submit File</button>
        </div>
    </div>
        {{ csrf_field() }}
        {{ method_field('PUT') }}
    </form>
@stop
