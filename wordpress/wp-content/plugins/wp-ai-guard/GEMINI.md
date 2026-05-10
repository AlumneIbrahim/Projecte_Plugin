# WP-AI-Guard Project Instructions

## Synchronization Workflow
All changes to the WP-AI-Guard plugin MUST be applied to both of the following locations simultaneously:
1.  **Project Directory (Git):** `wordpress/wp-content/plugins/wp-ai-guard/`
2.  **Server Directory (XAMPP):** `/opt/lampp/htdocs/wordpress/wp-content/plugins/wp-ai-guard/`

This ensures that the code remains under version control while being immediately testable in the local development environment.

## Ownership & Permissions
When updating files in the XAMPP directory, ensure the ownership remains `daemon:daemon` and permissions are set to `755` for directories and `644` for files (or as required by the environment) to prevent 403 Forbidden errors.
