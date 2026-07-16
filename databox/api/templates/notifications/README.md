# Notification templates (alchemy/notifier-bundle)

One folder per topic (dots -> slashes), one file per channel:

    <topic>/email.html.twig   # blocks: subject, body
    <topic>/in_app.html.twig  # blocks: subject, body
    <topic>/sms.txt.twig      # body only

Example: topic `asset.comment` -> `asset/comment/email.html.twig`
