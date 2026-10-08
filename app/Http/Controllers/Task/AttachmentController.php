<?php

namespace App\Http\Controllers\Task;

use App\Actions\Task\CreateTask;
use App\Events\Task\AttachmentDeleted;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorize('viewAny', [Attachment::class, $project]);

        $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120'], // 5 MB por archivo
        ]);

        $files = (new CreateTask)->uploadAttachments($task, $request->attachments);

        return response()->json(['files' => $files]);
    }

    public function destroy(Project $project, Task $task, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', [$attachment, $project]);

        Storage::disk('external')->delete(ltrim(str_replace('/imagenes/', '', $attachment->path), '/'));
        Storage::disk('external')->delete(ltrim(str_replace('/imagenes/', '', $attachment->thumb), '/'));

        $attachment->delete();

        AttachmentDeleted::dispatch($task, $attachment->id);

        return response()->json();
    }
}
