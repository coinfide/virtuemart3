# Coinfide plugin for Virtuemart 3

## General info

This plugin is under heavy development, so feel free to submit [bugs and enhancements to our issue tracker](https://github.com/coinfide/virtuemart3/issues).

## Installation

Copy-paste it to ```plugins/vmpayment/coinfide``` folder. Run: 

```
INSERT INTO `#__extensions` (`extension_id`,  `type`, `name`, `element`, `folder`, `access`, `ordering`, `enabled`, `protected`, `client_id`, `checked_out`, `checked_out_time`, `params`)
VALUES
 (NULL, 'plugin', 'plg_vmpayment_coinfide', 'coinfide', 'vmpayment', 1, 0, 1, 0, 0, 0, '0000-00-00 00:00:00', '');
```

Zipballs for upload installation will be provided later.

## Further reading

Read full documentation in the [wiki](https://github.com/coinfide/documentation/wiki)
