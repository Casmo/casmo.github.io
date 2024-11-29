---
layout: post
title: Recover deleted Uploadcare files in Laravel
---

Whoops, it could happen everyone. But this time it happend to me. I tried to configure Uploadcare in Filament and after uploading a new image everything was deleted in Uploadcare! Luckely for me, restoring all deleted files wasn't hard but not well documented. Here is a simple snippet.

-----

{% highlight php linenos %}
// web.php

use Illuminate\Support\Facades\Http;

Route::get('/test', function () {
    $result = Http::withHeaders([
            'Authorization' => 'Uploadcare.Simple public_key:private_key',
        ])
        ->get('https://api.uploadcare.com/files/?removed=true')
        ->throw()
        ->json();

    foreach ($result['results'] as $file) {

        Http::withHeaders([
            'Authorization' => 'Uploadcare.Simple public_key:private_key',
        ])
            ->put('https://api.uploadcare.com/files/'. $file['uuid'] .'/storage/')
            ->throw()
            ->json();

        dump($file['uuid'] .' recovered.');
    }
});
{% endhighlight %}