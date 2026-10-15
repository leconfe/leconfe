<?php

namespace App\Http\Middleware;

use App\Facades\MetaTag;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the translatable context content with frontend views. Runs after SetLocale
 * so the values follow the visitor's language.
 */
class ShareLocalizedContextData
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isInstalled()) {
            return $next($request);
        }

        if ($scheduledConference = app()->getCurrentScheduledConference()) {
            $this->share(
                $scheduledConference->translated('title'),
                $scheduledConference->translated('page_footer'),
                $scheduledConference->translated('description'),
                $scheduledConference->getMeta('meta_tags') ?? [],
            );

            return $next($request);
        }

        if ($conference = app()->getCurrentConference()) {
            $this->share(
                $conference->translated('name'),
                $conference->translated('page_footer'),
                $conference->translated('description'),
                $conference->getMeta('meta_tags') ?? [],
            );

            return $next($request);
        }

        $site = app()->getSite();

        $this->share(
            $site->translated('name'),
            $site->getMeta('page_footer'),
            $site->translated('description'),
        );

        return $next($request);
    }

    protected function share(?string $name, ?string $pageFooter, ?string $description, array $metaTags = []): void
    {
        View::share('headerLogoAltText', $name);
        View::share('contextName', $name);
        View::share('pageFooter', $pageFooter);

        MetaTag::add('description', preg_replace("/\r|\n/", '', (string) $description));

        foreach ($metaTags as $metaName => $content) {
            MetaTag::add($metaName, $content);
        }
    }
}
