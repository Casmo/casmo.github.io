---
layout: post
title: Search (part) of an email address in mysql with fulltext.
---

Whenever you work with searching in MySQL you are quickly using fulltext in boolean mode. This is a powerful way to finetune a search but has it limitions with special characters, like the at-sign.

-----

When searching (parts of) an email address in MySQL you probably encountered the following error:

```
MYSQL - syntax error, unexpected '@', expecting $end
Error Code: 1064 syntax error, unexpected $end, expecting FTS_TERM or FTS_NUMB or '' MYSQL
```

Here is a snipped to search anything like:
@example.com
someone@
someone@example.com

{% highlight php linenos %}

$examples = [
    'someone@example.com',
    'someone@',
    '@example.com'
];

foreach ($examples as $query) {

    $search = '';

    if(str_contains($query, '@')) {
        // Split email by @
        $emailParts = explode('@', $query);

        if ($emailParts[0]) {
            $search .= '+"'. trim($emailParts[0]) .'@" ';
            if ($emailParts[1]){
                $search .= '+'. trim($emailParts[1]) .' ';
            }
        }
        else if($emailParts[1]){
            $search .= '+"@'. trim($emailParts[1]) .'" ';
        }
    }

    $query = Model::query()
        ->whereRaw('MATCH(`body`) AGAINST(? IN BOOLEAN MODE)', ["'". $search ."'"])
        ->get();

}

{% endhighlight %}