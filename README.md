# wp-brewing

## A WordPress plugin to render home brew recipes from Kleiner Brauhelfer, Brewfather and BeerSmith3.

This is an uggly piece of PHP code, that has been initiated as a very
personal attempt to render my brew recipes on my WordPress site with
as little redundancy as possible. Since March 2018 it grew steadily
and a recent larger step was a switch from using the "Kleiner Brauhelfer"
to the fabulous Brewfather web application.

So far, I do not care about a smooth WordPress integration, i18n, and
some other aspects. So, this is code is as it is. Take it or leave it.
However, if you would be willing to contribute, you are very welcome.

### License

See [LICENSE.txt][2]

### Minimal documentaion

To get started:

- Install the plugin, the Makefile should give a hint, which files
  have to be copied.
- Activate the plugin and go the the WP brewing settings page and
  do your adjustments.
- Now, it should be possible to use some new shortcodes, e.g.:
```
[brew-recipe source="bf" title="#022 Wilma Saison" /]
```
```
[brew-recipe source="kbh" title="#020*" /]
```
```
[brew-recipes year="2019" title="#*" /]
```
```
[brew-recipes mode='steuer' year="2018" source="kbh"/]
```

You can see things in action on [my personal blog][1].

[1]: https://frankensteiner.familie-steinberg.org
[2]: LICENSE.txt
