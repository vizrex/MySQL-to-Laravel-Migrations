@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">MySQL Database to Laravel Migrations Generator</div>

                <div class="panel-body">
                    <div id="lblMsg">
                        <p>
                            File has been uploaded successfully!
                            <br/>
                            Please wait while it is being processed...!
                        </p>
                    </div>
                    <div id="tablesList"></div>
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
                    $.ajax(
                    {
                        url: "{{route('dump.process')}}",
                        headers: { 'X-CSRF-TOKEN': $('input[name="_token"]').val() },
                        method: "POST",
                        dataType: "JSON",
                        data:
                                {
                                    localFileName: "{{$localFileName}}"
                                },
                        success: function(importedTableNames)
                        {
                            // We've code table names that were created in result of uploaded dump file
                            console.log(importedTableNames);
                            showTableNames(importedTableNames);
                        },
                        error: function(response)
                        {
                            console.log(response);
                            alert("An error has occurred while processing the given dump file.\nMake sure that you have uploaded a valid MySQL Dump file and it does not contain any CREATE DATABASE, DROP DATABASE, USE DATABASE, qualified names of schema objects or similar statements!");
                            $("#lblMsg").html(response.responseJSON.errorMsg).addClass("text-danger");
                        }
                    });
                });
                
                function showTableNames(tableNames)
                {
                    $("#lblMsg").html("<label>Select the tables for which you want to generate migrations:</label>");
                    
                    var form = $(document.createElement("form")).prop({action: "{{route('migrations.generate')}}", method: "POST"});
                    $(form).append('{{csrf_field()}}');
                    $.each(tableNames, function(index, tableName)
                    {
                       $(form).append(createCheckbox(tableName));
                    });
                    
                    if(tableNames.length > 0)
                    {
                        $(form).append($(document.createElement("button")).addClass("btn btn-default").prop({type: 'button'}).css("margin-right", "5px").html("<span class='glyphicon glyphicon-hand-up'></span> Toggle Selection").on('click', toggleSelection));
                        $(form).append($(document.createElement("button")).addClass("btn btn-success").prop({type: 'submit', id: 'btnGenerateMigrations', disabled: 'disabled'}).html("<span class='glyphicon glyphicon-triangle-right'></span> Generate Migrations"));
                    }
                    
                    $("#tablesList").html(form);
                }
                
                function createCheckbox(tableName)
                {
                    return $(document.createElement("div")).addClass("checkbox")
                    .append($(document.createElement("label"))
                            .append($(document.createElement("input"))
                                .prop({value: tableName, name:"tables[]", type: "checkbox"})
                                .on('change', tableSelectionChanged))
                            .append(tableName));
                    
                }
                
                function toggleSelection()
                {
                    $("input[type=checkbox]").each(function(index, checkbox)
                    {
                       var isChecked = $(checkbox).prop('checked');
                       isChecked = $(checkbox).prop('checked', !isChecked);
                    });
                    
                    tableSelectionChanged(); // Need to call it manually to enable/disable 'Generate Migrations' button
                }
                
                function tableSelectionChanged()
                {
                    if($("input:checked").length > 0)
                    {
                        $("#btnGenerateMigrations").removeAttr("disabled");
                    }
                    else
                    {
                        $("#btnGenerateMigrations").attr("disabled", "disabled");
                    }
                }
            </script>
    @endsection
@endsection
