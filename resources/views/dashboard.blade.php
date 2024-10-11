@extends('layout.layout')
@section('content')
    @if (session('error'))
    <div class="mt-3">
        <div class="alert alert-danger" role="alert">
            {{ session('error') }}
        </div>
    </div>
    @endif
    <form method="POST" enctype="multipart/form-data" action="{{route('dashboard.store')}}">
    <div class="row mt-3">
        <div class="col-12">
            <h4>Upload File</h4>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-12">
            <label for="formFile" class="form-label">Upload File CSV</label>
            <input class="form-control required" type="file" name="file" id="formFile" required accept=".csv">
        </div>
    </div>
    <div class="row  mb-3">
        <div class="col-12 form-floating">
        <select class="form-select" id="floatingSelect" aria-label="Choose Logic Select" name="logic" required>
            <option value="a" selected>Logika A</option>
            <option value="b">Logika B</option>
        </select>
        <label for="floatingSelect">Gunakan Logika</label>
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
