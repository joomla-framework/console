## Updating from v3 to v4

The following changes were made to the Console package between v3 and v4.

### Minimum supported PHP version raised

All Framework packages now require PHP 8.3 or newer.

### Return type of `DescriptorHelper::getName()` has been set to string

To adapt to the changes in the Symfony class, the return type of `DescriptorHelper::getName()` has been now declared as string.
