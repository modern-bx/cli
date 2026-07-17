# GitHub Pages placeholder

This directory intentionally exists so that a legacy GitHub Pages setting that
points to `/docs` does not try to run the default Jekyll builder against a
missing directory.

The actual documentation source is in `site/docs` and is published by the
`Publish documentation to GitHub Pages` workflow only when the repository
variable `USE_GITHUB_PAGES` is set exactly to `true`.
