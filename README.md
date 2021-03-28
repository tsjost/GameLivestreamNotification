# Game Livestream Notification
Get a notification when somebody starts streaming a specific game on Twitch

## Example
![](https://github.com/tsjost/GameLivestreamNotification/blob/assets/screenshot01.png)

## Setting up
1. Clone the repo
2. Copy `config.sample.php` to `config.php`
3. Right click the Discord channel you want notifications to appear in,  
click `Edit Channel`, go to the `Integrations` tab and click `Create Webhook`:
![](https://github.com/tsjost/GameLivestreamNotification/blob/assets/discord01.png)
4. Click `Copy Webhook URL` and you'll get an URL like  
`https://discord.com/api/webhooks/12345678987654321/hdKJHuiahwnejdjAKHUIhdkJAHWDiuawhdkJAHDuiawehkjA`  
Separated by `/` there are 2 important parts: the numeric one is the `Webhook ID`, and the long part at the end is the `Webhook Token`. Copy these into their respective locations in `config.php`
5. Use something like https://dev.twitch.tv/docs/api/reference#get-games to get the Game's ID on Twitch and stick it in `config.php`
6. Get your [Twitch token & Client ID](https://dev.twitch.tv/docs/authentication#getting-tokens) and stick them in `config.php`
7. Set `DISCORD_MESSAGE_PREFIX` in `config.php` to whatever you'd like prepended to the notification message.  
For example if your [Discord User ID](https://support.discord.com/hc/en-us/articles/206346498-Where-can-I-find-my-User-Server-Message-ID-) is 1234567 you can use `<@1234567>` to notify yourself
8. Execute `fetch.php`, either directly in your terminal, or through a cronjob, or whatever floats your arbitrary precision arithmetics.
