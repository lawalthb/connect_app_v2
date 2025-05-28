<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\FileUploadHelper;

class SendMessageRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'type' => 'required|in:text,image,video,audio,file,location',
            'reply_to_message_id' => 'nullable|exists:messages,id',
        ];

        if ($this->type === 'text') {
            $rules['message'] = 'required|string|max:4000';
        } elseif (in_array($this->type, ['image', 'video', 'audio', 'file'])) {
            $rules['file'] = 'required|file';
            $rules['message'] = 'nullable|string|max:1000';

            $this->addFileValidationRules($rules, $this->type);
        } elseif ($this->type === 'location') {
            $rules['latitude'] = 'required|numeric|between:-90,90';
            $rules['longitude'] = 'required|numeric|between:-180,180';
            $rules['address'] = 'nullable|string|max:500';
            $rules['message'] = 'nullable|string|max:1000';
        }

        return $rules;
    }

    private function addFileValidationRules(&$rules, $type)
    {
        $maxSizes = FileUploadHelper::getMaxFileSizes();
        $maxSizeKB = $maxSizes[$type] * 1024;

        switch ($type) {
            case 'image':
                $rules['file'] .= "|max:$maxSizeKB|mimes:jpeg,png,gif,webp,bmp|dimensions:max_width=4000,max_height=4000";
                break;
            case 'video':
                $rules['file'] .= "|max:$maxSizeKB|mimes:mp4,avi,mov,wmv,webm,3gp";
                break;
            case 'audio':
                $rules['file'] .= "|max:$maxSizeKB|mimes:mp3,wav,ogg,aac,m4a";
                break;
            case 'file':
                $rules['file'] .= "|max:$maxSizeKB|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,zip,rar";
                break;
        }
    }
}
