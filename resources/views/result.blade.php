@extends('layout.layout2')
@section('content')
    <div class="row col-12">
    @if($logic == "a")
        <h3 class="text-center">Jadwal Kunjungan Sales Logika A</h3>
    @else
        <h3>Jadwal Kunjungan Sales Logika B</h3>
    @endif
    </div>
    {!! $table_html !!}
@stop
