# Level Modification File

[Source Format](./SourceFormat.md)

The level modification file lays out various level-specific modifications that apply to a specific level. As a primary asset, this must include the correct header and must import the [`LinkDefs`](./LinkDefsImport.md) node.

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

## Common Types

## Main Node Types

The following nodes define the major modificatons. Generally, each one will be compiled into a distinct chunk within the generated asset binary for the level

### PVSErrata

### ZoneErrata

### ZoneMessages

### ObjectMessages

