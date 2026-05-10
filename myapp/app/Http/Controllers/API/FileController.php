<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function index(Request $request)
    {
        $files = $request->user()->files()->latest('created_at')->get();
        return new FileResource(true, 'Files retrieved successfully', $files);
    }

    
}
