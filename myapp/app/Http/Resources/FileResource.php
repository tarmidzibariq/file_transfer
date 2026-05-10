<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public $status;
    public $message;

    public function __construct($status, $message, $resource)
    {
        parent::__construct($resource);
        $this->status  = $status;
        $this->message = $message;
    }

    public function toArray($request)
    {
        $data = $this->resource;

        if ($data instanceof JsonResource) {
            $data = $data->resolve($request);
        }

        return [
            'success' => (bool) $this->status,
            'message' => $this->message,
            'data' => $data,
        ];
    }
}
