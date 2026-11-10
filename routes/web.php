<?php

/*
| Each feature area owns one file in routes/web/. They are all loaded here so
| features can be added without editing a shared file.
*/
foreach (glob(__DIR__.'/web/*.php') as $routeFile) {
    require $routeFile;
}
