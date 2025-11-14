# Keycloak: Roles vs Scopes

## 1. Real-Life Analogy

### Role
Imagine you are in a company:
- A **role** corresponds to a **job position** (e.g., "Manager", "Developer", "Accountant").
  - A role defines **what you are allowed to do** based on your position.
  - Example: A "Manager" can approve leave requests, a "Developer" can access the source code.

### Scope
Now imagine you want to access an external service (e.g., an expense report application):
- A **scope** corresponds to **what the application is allowed to know or do** with your identity.
  - Example: When you log in to the databox application with your account, you can authorize the application to:
    - **"Read your profile"** (scope: `profile`)
    - **"Create assets"** (scope: `asset:write`)
  - The scope does not define **who you are**, but **what the application can do with your data**.

---

## 2. Technical Differences in Keycloak

| **Aspect**      | **Role**                                      | **Scope**                                      |
|-----------------|-----------------------------------------------|-----------------------------------------------|
| **Definition**  | Assigned to a user or group.                  | Defines the **permissions** requested by an application. |
| **Usage**       | Controls access to **internal** resources (e.g., access to an admin dashboard). | Controls **delegated authorizations** to a third-party application (e.g., read/write access). |
| **Example**     | `admin`, `editor`,`user`                  | `openid`, `profile`, `email`, `asset:read`      |
| **Scope**       | Tied to the user's identity.                  | Tied to an application's **access request**. |

---

## 3. Are Roles and Scopes Specific to Keycloak?
No, these concepts exist in most **modern authorization protocols** (OAuth 2.0, OpenID Connect).

### Where Are They Found?
- **OAuth 2.0/OpenID Connect**: Scopes are standardized (e.g., `openid`, `profile`, `email`).
- **Other Providers**:
  - **Auth0**: Uses roles and scopes in the same way.
  - **Okta**: Similar, with roles for internal permissions and scopes for delegated authorizations.
  - **Azure AD**: Refers to roles as "application roles" and uses scopes for API permissions.
  - **Google OAuth**: Uses scope to limit access to data (e.g., `https://www.googleapis.com/auth/drive.readonly`).

---

## 4. Summary Table

| **Concept**  | **Role**                          | **Scope**                          |
|--------------|-----------------------------------|------------------------------------|
| **Analogy**  | Your job position in the company. | What you authorize an app to do with your account. |
| **Keycloak** | Assigned via the admin console.   | Defined in OAuth clients.          |
| **Standard** | Not standardized (provider-specific). | Standardized (OAuth 2.0). |

---

## 5. Practical Use Case
- **Role**: "Jennifer is an `admin` in the Keycloak realm → she can manage users."
- **Scope**: "The 'Mobile App' requests the `asset` scope → it can read user's name and email, but cannot modify his data."
