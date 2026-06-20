<?php

namespace App\Models;

use App\Application;
use App\Mail\Templates\TemplateMailable;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Spatie\MailTemplates\Models\MailTemplate as BaseMailTemplate;
use Sushi\Sushi;

class DefaultMailTemplate extends BaseMailTemplate
{
    use Sushi;

    protected $table = 'mail_templates';

    public static $data = [];

    protected $schema = [
        'id' => 'integer',
        'conference_id' => 'integer',
        'mailable' => 'string',
        'description' => 'string',
        'subject' => 'string',
        'html_template' => 'string',
        'text_template' => 'string',
        'custom' => 'boolean',
    ];

    public function getRows()
    {
        return $this->getData(app()->getCurrentConference());
    }

    public function getData(Conference $conference)
    {
        if (empty($this->data)) {
            $this->data = $this->getDefaultData($conference);
        }

        return $this->data;
    }

    public function getDefaultData(Conference $conference)
    {
        $data = [];

        $directory = app_path('Mail/Templates');
        $namespace = 'App\\Mail\\Templates';

        $filesystem = app(Filesystem::class);
        if ((! $filesystem->exists($directory)) && (! str($directory)->contains('*'))) {
            return;
        }

        $customTemplates = MailTemplate::all();
        $namespace = str($namespace);
        foreach ($filesystem->allFiles($directory) as $file) {
            $variableNamespace = $namespace->contains('*') ? str_ireplace(
                ['\\'.$namespace->before('*'), $namespace->after('*')],
                ['', ''],
                str($file->getPath())
                    ->after(base_path())
                    ->replace(['/'], ['\\']),
            ) : null;

            if (is_string($variableNamespace)) {
                $variableNamespace = (string) str($variableNamespace)->before('\\');
            }

            $class = (string) $namespace
                ->append('\\', $file->getRelativePathname())
                ->replace('*', $variableNamespace)
                ->replace(['/', '.php'], ['\\', '']);

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            if (property_exists($class, 'deprecatedTemplateAlias') && $class::$deprecatedTemplateAlias) {
                continue;
            }

            if (! is_subclass_of($class, TemplateMailable::class)) {
                continue;
            }

            $defaultData = [
                'conference_id' => $conference?->getKey() ?? Application::CONTEXT_WEBSITE,
                'mailable' => $class,
                'description' => $class::getDefaultDescription(),
                'subject' => $class::getDefaultSubject(),
                'html_template' => $class::getDefaultHtmlTemplate(),
                'text_template' => $class::getDefaultTextTemplate(),
                'custom' => false,
            ];

            $customTemplate = $customTemplates->where('mailable', $class)->first();
            if ($customTemplate) {
                $defaultData['description'] = $customTemplate->description;
                $defaultData['subject'] = $customTemplate->subject;
                $defaultData['html_template'] = $customTemplate->html_template;
                $defaultData['text_template'] = $customTemplate->text_template;
                $defaultData['custom'] = true;
            }

            $data[] = $defaultData;
        }

        return $data;
    }
}
