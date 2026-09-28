# Permission Testing Structure

This document explains the structure and methodology used for testing permissions in Phrasea DAM.

## Overview
Phrasea DAM implements fine-grained access control for users and groups. Permission tests validate that users can only perform actions allowed by their assigned permissions. The test suite ensures that permissions are correctly inherited, applied, and enforced at all levels of the asset hierarchy.

## Test Hierarchy
For each test case, we use the same underlying structure of a workspace with nested collections and assets. This allows us to test permissions at various levels of the hierarchy, ensuring that permissions are correctly inherited and applied.

```
Workspace "Sandbox" (owned by root)
|- Asset "Lost-alice" (owned by alice)
|- Asset "Lost-bob" (owned by bob)
|- Collection "A" (owned by alice)
|  |- Asset "InA-alice" (owned by alice)
|  |- Asset "InA-bob" (owned by bob)
|  |- Collection "B" (owned by bob)
|  |  |- Asset "InB-alice" (owned by alice)
|  |  |- Asset "InB-bob" (owned by bob)
```

### Users
- **root**: Owner of the workspace, full permissions. Can perform all actions on all entities.
- **alice**: Owns some assets and collections. Used to test partial permissions and ownership scenarios.
- **bob**: Owns some assets and collections. Used to test partial permissions and ownership scenarios.
- **carol**: Used for additional test cases, typically with no permissions.

## Test Case Structure
Each test case is represented by a `PermissionsTestCase` object, which defines:
- Username
- Permissions for each entity (workspace, collections, assets)
- Expected abilities (view, edit, delete, create, upload) for each entity

## Test Execution
The test suite:
- Sets up the workspace, collections, and assets with their respective owners
- For each test case:
- Assigns permissions to the user based on the defined test case
- resetting/applying permissions
- Asserts expected abilities of user using Symfony's security voters
