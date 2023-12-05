<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Modules\Admin\Facades\Admin;
use Modules\Admin\Entities\SukoAtiModel;
use Inertia\Inertia;
use App\Traits\ListMidleware;
use Auth;
class SukoatiController extends Controller
{
    use ListMidleware;
    public function __construct(Request $request)
    {
        $slug = $this->getSlug($request);
        $midlw = ['role_or_permission:admin|'.$this->namaMidlewarePermission($slug)];
        $this->middleware($midlw);

        
    }
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $slug = $this->getSlug($request);
        if (Auth::user()->hasAnyPermission([$slug.'-index']) ){
            $dataType = Admin::model('DataType')->with(['rows' => function ($query) {
                $query->where('browse', '1');
            }])->where('slug', '=', $slug)->first();
            if (class_exists($dataType->model_name)) {
                // jika model ada
                $model= app($dataType->model_name);
            
            } else {
                // Model tidak ada
                // $model= app('Modules\Admin\Entities\SukoAtiModel');
                // $model->setTableName($dataType->name);
                $model = new SukoAtiModel();
            }
            
            
            $dataheader = $dataType->rows->map(function($item){
                return [
                    'title'=> $item->display_name,
                    'field'=> $item->field,
                    'type'=> $item->type,
                    'size'=>'auto',
                    'align'=> 'left'
                ];
            });
    
            $aksi =collect( [
                    'title' => 'Aksi',
                    'field' => null,
                    'type' => 'button-group',
                    'data' => [
                        [
                            'text'=> '',
                            'type'=> 'button',
                            'action'=> 'edit',
                            'class'=> 'border rounded-l-2xl border-blue-500 hover:bg-blue-500 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-700 focus:bg-blue-500 focus:text-white focus:z-[1]',
                            'icon'=> 'fas fa-lg fa-pencil-alt'
                        ],
                        [
                            'text'=> '',
                            'type'=> 'button',
                            'action'=> 'lihatDetail',
                            'class'=> 'border-t border-b border-blue-500 hover:bg-emerald-500 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-700 focus:bg-emerald-500 focus:text-white focus:z-[1]',
                            'icon'=> 'fas fa-lg fa-file-alt'
                        ],
                        [
                            'text'=> '',
                            'type'=> 'button',
                            'action'=> 'hapus',
                            'class'=> 'border rounded-r-2xl border-blue-500  hover:bg-red-500 hover:text-white focus:outline-none focus:ring-2 focus:ring-red-700 focus:bg-red-500 focus:text-white  focus:z-[1]',
                            'icon'=> 'fas fa-lg fa-trash-alt'
                        ],
                    ],
                    'size'=> 20,
                    'align'=> 'center'
                
            ]);
            $header = $dataheader->push($aksi);
    
            if ($request->has('search')) {
                $pencarian = $header->pluck('field');
                $data = $model->query();
                foreach ($pencarian as $pencarian) {
                    $data->orWhere($pencarian, 'like', '%' . $request->search . '%');
                }
    
                $listData = $data->from($dataType->name)
                ->orderBy('id', 'desc')
                ->paginate(10);
                $listData->appends ( array (
                    'search' => $request->search
                ) );
                
            }else{
                $listData = $model->from($dataType->name)
                ->orderBy('id', 'desc')
                ->paginate(10);
            }
        
            return Inertia::render('Admin/Sukoati/Index',[
                'header'=>$header,
                'slug' =>  $slug,
                'data'=> $listData,
                'dataSearch'=> $request->search,
                'titleTable'=> $dataType->display_name_singular,
                
            ]);

        }else{
            return 'tidak ada permission';
        }

      

        
      
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create(Request $request)
    { 
        $slug = $this->getSlug($request);
        $dataType = Admin::model('DataType')->with(['rows'])->where('slug', '=', $slug)->first();

        $shema= $dataType->rows->flatMap(function($item){
        $rules = [];
        if($item->required === 1){
            array_push($rules,'required');
        }
        if ($item->add === 1) {
            # code...
            return [
                $item->field =>[
                    'type'=> $item->type,
                    'label' => $item->display_name,
                    'floating' => false,
                    'placeholder' => $item->display_name,
                    'fieldName' => $item->display_name,
                    'rules' => $rules,
                    'columns' => array(
                        'container' => 6,
                        'label' => 12,
                        'wrapper' => 12,
                    ),
                    'overrideClass' => array(
                        'inputContainer' => 'border border-gray-300 w-full transition-all rounded-lg shadow-sm',
                        'inputContainer_default' => 'border-black',
                        'inputContainer_focused' => '',
                        'inputContainer_md' => '',
                    ),
                    'addClasses' => array(
                        'ElementLabel' => array(
                            'container' => 'block font-medium text-sm text-gray-700',
                        ),
                        'TextElement' => array(
                            'input' => 'rounded-lg shadow-sm',
                        ),
                    ),

                ]
            ];
        }
            
        });
        $shema['element']=[
                'type'=> 'button',
                'button-label'=>'Simpan',
                'align'=>'right',
                'submits'=>true
                
            ];
         $container = collect(
            ['container'=> [
                'type'=> 'group',
                'schema'=> $shema
            ]]
        );
        return Inertia::render('Admin/Sukoati/Add',[
            'formContainer'=>$container,
            'action' =>  $slug,
            'display_name'=>$dataType->display_name_singular
        ]);
    
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {

        try {
            
            $slug = $this->getSlug($request);
            $dataType = Admin::model('DataType')->where('slug', '=', $slug)->first();

            if (class_exists($dataType->model_name)) {
                // jika model ada
                $model= app($dataType->model_name);
            
            } else {
                // Model tidak ada
                $model= app('Modules\Admin\Entities\SukoAtiModel');
                $model->setTableName($dataType->name);
            }
            $model->create($request->all());
            return back(303)->with(['message'=>'Sukses Simpan Data']);
        } catch (\Illuminate\Database\QueryException $e) {
            $errors = new MessageBag(['error' => [$e->errorInfo[2]]]);
            return back()->withErrors($errors);
        }
        
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('admin::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('admin::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

}
