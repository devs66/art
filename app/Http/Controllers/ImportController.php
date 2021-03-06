<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;

use App\Import;
use App\Jobs\ImportCsv;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Translation\Translator;

class ImportController extends Controller
{
    protected $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $imports = Import::orderBy('created_at', 'DESC')->paginate(10);
        return view('imports.index')->with('imports', $imports);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('imports.form');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        $input = Request::all();

        $rules = Import::$rules;
        $v = Validator::make($input, $rules);

        if ($v->passes()) {

            $import = new Import;
            $import->name = Request::input('name');
            $import->class_name = Request::input('class_name');
            $import->dir_path = Request::input('dir_path');
            $import->iip_dir_path = Request::input('iip_dir_path');
            $import->save();

            return redirect()->route('imports.index');
        }

        return back()->withInput()->withErrors($v);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $import = Import::with(['records' => function($query) {
            $query->orderBy('id', 'desc');
            $query->take(50);
        }])->find($id);
        return view('imports.show')->with('import', $import);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $import = Import::find($id);

        if (is_null($import)) {
            return redirect()->route('import.index');
        }

        $options = $import->class_name::getOptions();

        return view('imports.form')->with(['import' => $import, 'options' => $options]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update($id)
    {
        $v = Validator::make(Request::all(), Import::$rules);

        if ($v->passes()) {
            $input = Arr::except(Request::all(), array('_method'));

            $import = Import::find($id);
            $import->name = Request::input('name');
            $import->class_name = Request::input('class_name');
            $import->dir_path = Request::input('dir_path');
            $import->iip_dir_path = Request::input('iip_dir_path');
            $import->save();

            if (Request::hasFile('file')) {
                $file = Request::file('file');
                $path_to_save = 'import/' . $import->dir_path . '/' . $file->getClientOriginalName();
                \Storage::put($path_to_save, \File::get($file));
            }


            \Session::flash('message', 'Import <code>'.$import->name.'</code> bol upraven??');
            return redirect()->route('imports.index');
        }

        return back()->withErrors($v);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $import = Import::find($id);
        $name = $import->name;
        $import->delete();
        return redirect()->route('imports.index')->with('message', 'Import <code>'.$name.'</code>  bol zmazan??');
        ;
    }

    /**
     * Launch the import
     *
     * @param  /App/Import $import
     * @param  CSV file $file
     * @return Response
     */
    public function launch($id)
    {

        // run in queue
        $import = Import::find($id);
        $import->status = Import::STATUS_QUEUED;
        $import->started_at = date('Y-m-d H:i:s');
        $import->save();

        $this->dispatch(new ImportCsv($import));

        return redirect()->route('imports.index')->with('message', 'Import <code>'.$import->name.'</code> bol spusten??');

    }


}
