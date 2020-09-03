<?php

function mimic($data = [], $override = false, $overrideType = 'replace')
{
    return \Flex360\Pilot\Pilot\Page::mimic($data, $override, $overrideType);
}

function drop($mixed, $relationships = [])
{
    return \Flex360\Pilot\Pilot\Helpers\Drop::make($mixed, $relationships);
}

function menu($slug, $class = null)
{
    $menu = \Flex360\Pilot\Pilot\Menu::findBySlug($slug);

    $menu->class = $class;

    return $menu;
}

function setting($key, $default = null)
{
    return \Flex360\Pilot\Pilot\Setting::get($key, $default);
}

function pmix($path, $manifestDirectory = '')
{
    try {
        $newPath = mix('/custom/' . trim($path, '/'), $manifestDirectory);
        if (strpos($newPath, '?') === false) {
            $newPath = mix($path, $manifestDirectory);
        }
        return $newPath;
    } catch (\Exception $e) {
        try {
            return mix($path, $manifestDirectory);
        } catch (\Exception $e) {
            return $path;
        }
    }
}

function pilotNav()
{
    return new \Flex360\Pilot\Pilot\Nav;
}

function pilotNavItem($name, $url)
{
    return new \Flex360\Pilot\Pilot\NavItem($name, $url);
}
