The packages that are likely to cause conflicts with other plugins (by loading multiple incompatible versions).
Their namespaces are isolated by [Mozart](https://github.com/coenjacobs/mozart).

Currently, the packages are simply added in the repo to avoid making the build process more complex (Mozart has different PHP requirements).
We need to isolate only PSR-11 containers and Dhii modularity packages, which are not supposed to change often.
