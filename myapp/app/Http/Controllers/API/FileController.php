<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function index(Request $request)
    {
        $files = $request->user()->files()->latest('created_at')->get();
        return new FileResource(true, 'Files retrieved successfully', $files);
    }

    public function upload(Request $request) {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'is_public' => 'nullable|boolean',
        ]);
        $file = $request->file('file');
        $path = $file->store('files', 'public'); 
        $fileRecord = $request->user()->files()->create([
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'path' => $path,
            'is_public' =>$request->input('is_public', false),
        ]);

        return new FileResource(true, 'File uploaded successfully', $fileRecord);
    }

    public function publicDownload(string $id)
    {
        $file = File::whereKey($id)->where('is_public', true)->firstOrFail();

        return response()->download(
            storage_path('app/public/' . $file->path),
            $file->original_name
        );
    }

    public function download(Request $request, $id) {
        $file = $request->user()->files()->findOrFail($id);
        return response()->download(storage_path('app/public/' . $file->path), $file->original_name);
    }

    public function delete(Request $request, $id) {
        $file = $request->user()->files()->findOrFail($id);
        if ($file->path) {
            Storage::disk('public')->delete($file->path);
        }
        $file->delete();
        
        return new FileResource(true, 'File deleted successfully', null);
    }

    public function toggleVisibility(Request $request, $id) {
        $file = $request->user()->files()->findOrFail($id);
        
        $file->update([
            'is_public' => !$file->is_public
        ]);

        return new FileResource(true, 'File visibility toggled successfully', $file);
    }
}
