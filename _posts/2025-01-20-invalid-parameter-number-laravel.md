---
layout: post
title: Invalid parameter number in Laravel Cursor Pagination
---

I was working with raw queries and selects in Laravel in combination with Laravel's Cursor Pagination. This worked fine until I want to the next page.

If you encounter the following error in combination with `selectRaw()` or `whereRaw()` in Laravel, I hope this solution works for you.

```
SQLSTATE[HY093]: Invalid parameter number (Connection: mysql, SQL: ...)
```

-----

The reason for this is that cursor pagination is not using `offset`, but rather `order by ?`. In my case, I was ordering the results by a field `_score`. Yours might be different.

{% highlight php linenos %}

$query = Model::query()
    ->selectRaw('MATCH(`subject`, `body`) AGAINST(? IN BOOLEAN MODE) as _score', ["'". $search ."'"])
    ->whereRaw('MATCH(`subject`, `body`) AGAINST(? IN BOOLEAN MODE) > 0', ["'". $search ."'"]);

if (request()->get('cursor')) {
    $cursorPaginator = CursorPaginator::resolveCurrentCursor('cursor', null);
    $query->addBinding($cursorPaginator->parameter('_score'), 'where'); // Make sure parameter _score is available
}

$result = $query->cursorPaginate(20);

dump($result);

{% endhighlight %}

Good luck!
