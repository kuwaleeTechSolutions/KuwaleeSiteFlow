<?php

namespace App\Http\Requests\DailyReport;

use App\Models\DailyReportPhoto;
use Illuminate\Foundation\Http\FormRequest;

class UploadDailyReportPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('upload', [DailyReportPhoto::class, $this->route('dailyReport')]);
    }

    public function rules(): array
    {
        $allowedMimes = implode(',', config('daily_reports.allowed_photo_mimes'));
        $maxKb = config('daily_reports.max_photo_size_kb');

        return [
            'photo' => [
                'required',
                'file',
                // 'mimetypes' validates the ACTUAL, server-detected MIME
                // type (via PHP's finfo/fileinfo extension), NOT the
                // client-supplied extension or Content-Type header — this
                // satisfies brief §6/§14's "never trust the original file
                // extension" requirement at the validation layer. A second,
                // independent check runs in PhotoUploadService before the
                // file is persisted (defense in depth).
                "mimetypes:{$allowedMimes}",
                "max:{$maxKb}",
            ],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
