# Level Modification File

[Back](./SourceFormat.md)

The level modification file lays out various level-specific modifications that apply to a specific level. As a primary asset, this must include the correct header and must import the LinkDefs node.

**Example:**

```
{
    Header: {
        Type: "Level",
        Description: "New Level A",
        Version: "1.0",
        Requires: "1.13",
    },
    Import: {
        LinkDefs: "common/linkdefs.rson",
    },

    // Remaining definitions
}
```
