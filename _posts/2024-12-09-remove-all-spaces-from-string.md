---
layout: post
title: Remove all (non breakable) spaces from a string in PHP
---

When working with emails you sometimes want to clear all html, spaces and other markup and just keep the plain text of a mail. Useful for indexing or searching in large amounts of emails.

Here is a snippet that removes all hard-to-get spaces, removes all html and decode special characters.

-----

{% highlight php linenos %}

function removeHtmlFromMail($body) {
    $body = trim(
        preg_replace('/([[:space:]\xc2\xa0\xe2\x80\x8c\xe2\x80\x8b\xe2\x80\x8d]+)/', ' ',
            html_entity_decode(
                strip_tags(
                    str_replace(['&lt;', '&gt;'], [' ', ' '],
                        $body
                    )
                ),
                encoding: 'UTF-8'
            )
        )
    );

    $body = mb_convert_encoding($body, 'UTF-8', 'auto');

    return $body;
}

echo removeHtmlFromMail('<span>email with a lot of unused html</span>');

{% endhighlight %}