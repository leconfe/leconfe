<?php

namespace App\Routing;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;

class CustomUrlGenerator extends UrlGenerator
{
    /**
     * Get the URL to a named route.
     *
     * @param  string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     *
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     */
    public function route($name, $parameters = [], $absolute = true)
    {
        $route = $this->routes->getByName($name);

        /**
         * Handle the conference parameters when the route needs them
         */
        if ($route) {
            if (Str::contains($route->uri(), '{conference}') && $scheduledConference = app()->getCurrentScheduledConference()) {
                $parameters['conference'] ??= $scheduledConference->path;
            }
        }

        return parent::route($name, $parameters, $absolute);
    }

    /**
     * Get the Route URL generator instance.
     *
     * @return \Illuminate\Routing\RouteUrlGenerator
     */
    protected function routeUrl()
    {
        if (! $this->routeGenerator) {
            $this->routeGenerator = new CustomRouteUrlGenerator($this, $this->request);
        }

        return $this->routeGenerator;
    }
}
