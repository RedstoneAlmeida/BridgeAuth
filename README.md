BridgeAuth
========
BridgeAuth is a centralized authentication platform that utilizes the EPICMC Bridge API.

This API cuts down on impersonation, hacking, and spam registrations.

To authenticate on the Bridge API a valid (and unique) email address is required.
You can register at https://epicmc.us/register

After verifying your email you'll sign into https://epicmc.us/account and claim a bridge_token. This is used instead of your password when authenticating on servers.

This plugin features a local cache, so you'll only need to enter your bridge_token on each server you join once. This functionality will be improved upon in the future.

You can reset your bridge_token whenever at https://epicmc.us/account. If you reset your token nobody will be able to authenticate with it.
