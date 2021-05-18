# C3 Cloudfront Cache Controller

Cloudfront cache management based on C3 Cloudfront Cache Controller by AMIMOTO and WP Offload S3 Lite by Delicious Brains

Needs Amazon Web Services 1.0.7 to work

set `define('DISTRIBUTION_ID', 'XXXX')` in wp-config.php

Otherwise install just like any other plugin

## Multiple distributions

If the site is a multisite or has language version-specific domains, you most likely have a distribution for each of them. To define multiple distros, use the format `DISTRIBUTION_ID_[blog id]_[language slug]`

e.g.  
`DISTRIBUTION_ID_1_EN` = multisite, main blog selected, english language  
`DISTRIBUTION_ID_EN` = no multisite, english language  
`DISTRIBUTION_ID_1` = multisite, main blog selected, no language-specific domain
