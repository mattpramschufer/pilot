@extends(config('dynamo.layout'))

@section('title', $dynamo->getName() . ' Manager')

@section('content')

    <div class="container-fluid pt-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-11 col-xl-10">
                <div class="card sidebar-card">
                    <div class="card-header">
                        Reorder Projects In This Category
                        <a href="/" target="_blank" id="preview-btn" class="btn btn-info btn-sm float-right"><i class="fa fa-eye"></i> Preview</a>
                        <a href="/pilot/projectcategory" class="btn btn-primary btn-sm float-right" style="margin-right: 10px;">Back to Project Categories</a>


                    </div>

                    <div class="card-body">

                        <input type="hidden" id="projectcategoryID" name="projectcategoryID" value="{{ $projectCategory->id }}">

                        @include('dynamo::partials.alerts')

                        @if ($items->isEmpty())

                            <div>No items found. <a href="{{ route($dynamo->getRoute('create')) }}">Add one.</a></div>

                        @else
                            <p>Drag-n-drop sort the Projects in the order you'd
                            like them to appear inside this ProjectCategory.
                            Click the "Preview" button and a new tab will open up to that category.
                            You can re-order the Projects again, and refresh the Preview tab until you get the order the way you
                            want it.</p>
                            <div class="table-responsive dynamo-table-responsive">
                                <table class="table" id="dynamo-index">
                                    <thead>
                                        <tr>
                                            <th>Sort</th>
                                            <th>Title</th>
                                            <th>Summary</th>
                                            <th style="width: 110px;">Action</th>
                                        </tr>
                                    </thead>
                                        <tbody id="dynamo-index-body">
                                            @foreach ($items as $item)
                                                <tr class="dynamo-index-row" data-id="{{ $item->id }}">
                                                  <td><i class="fas fa-bars fa-2x"></i></td>
                                                    <td>{!! $dynamo->getIndexValue('title', $item) !!}</td>
                                                    <td>{!! $item->getSummaryBackend() !!}</td>
                                                    <td>
                                                        <a href="{{ route($dynamo->getRoute('edit'), $item->id) }}" class="btn btn-secondary btn-sm">Edit</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                </table>
                            </div>


                            {!! method_exists($items, 'render') ? $items->appends(request()->only(['q']))->render() : null !!}

                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .panel-body .table { margin-bottom: 0; }
    </style>
@endsection

@section('scripts')
    <script src="/pilot-assets/legacy/js/projectcategory-projects.js"></script>
    <script>
    $(document).ready(function(){
        $('.btn-delete').click(function(){
            return confirm('Are you sure?');
        });

        //Get Preview link node
        var currentUrl = window.location.pathname;
        var categoryNumber = currentUrl.replace(/\D/g,'');
        console.log(categoryNumber);
        var previewUrl = '/projects/' + categoryNumber + '/category';
        $("#preview-btn").attr("href", previewUrl);
    });
    </script>
@endsection
