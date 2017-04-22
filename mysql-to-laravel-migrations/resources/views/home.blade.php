@extends('layouts.app')

@section('head')
<link rel="stylesheet" href="{{URL::to('/').'/public/css/fileinput.min.css'}}">
@endsection

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">MySQL Database to Laravel Migrations Generator</div>

                <div class="panel-body">
                    <form action="{{route('dump.upload')}}" method="POST" enctype="multipart/form-data">
                        {{csrf_field()}}
                        <div class="form-group">
                            <label for="inputFile">Select MySQL Dump File </label>
                            <input type="file" class="form-control" id="inputFile" name="inputFile" placeholder="MySQLDump.sql" accept=".sql">
                            <p class="help-block">Currently we support (.sql) file format only.</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

    @section('postContent')
        <script src="{{ URL::to('/').'/public/js/fileinput.min.js' }}"></script>
            <script type="text/javascript">
                $(document).ready(function()
                {
                   $("#inputFile").fileinput(
                   {
                    showUpload: true,
                    browseLabel: "Browse",
                    showPreview: false,
                    allowedFileExtensions: ['sql']
                   }); 
                });
            </script>
    @endsection
@endsection
