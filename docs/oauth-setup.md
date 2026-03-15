# OAuth Setup

Strava uses OAuth 2.0 for API access. This guide walks you through generating the tokens you need.

## Step 1: Create a Strava API Application

1. Go to [strava.com/settings/api](https://www.strava.com/settings/api)
2. Fill in the application form:
   - **Application Name**: Your site name
   - **Category**: Choose the most appropriate
   - **Website**: Your site URL
   - **Authorization Callback Domain**: `localhost`
3. Note your **Client ID** and **Client Secret**

## Step 2: Authorise Your App

Visit this URL in your browser, replacing `YOUR_CLIENT_ID`:

```
https://www.strava.com/oauth/authorize?client_id=YOUR_CLIENT_ID&response_type=code&redirect_uri=http://localhost&scope=read,activity:read_all&approval_prompt=force
```

Click **Authorize** on the Strava page. You'll be redirected to `localhost` with a `code` parameter in the URL:

```
http://localhost/?state=&code=AUTHORIZATION_CODE&scope=read,activity:read_all
```

Copy the `code` value.

## Step 3: Exchange Code for Tokens

Run this curl command, replacing the placeholders:

```bash
curl -X POST https://www.strava.com/oauth/token \
  -d client_id=YOUR_CLIENT_ID \
  -d client_secret=YOUR_CLIENT_SECRET \
  -d code=AUTHORIZATION_CODE \
  -d grant_type=authorization_code
```

The response contains your tokens:

```json
{
  "access_token": "abc123...",
  "refresh_token": "def456...",
  "expires_at": 1234567890,
  "athlete": { "id": 12345 }
}
```

## Step 4: Enter Tokens in Plugin Settings

Go to **Strava → Settings** and enter:

- **Access Token** — `access_token` from the response
- **Refresh Token** — `refresh_token` from the response
- **Token Expires At** — `expires_at` from the response

Click **Save Settings**, then **Resync Activities**.

## Token Refresh

The plugin handles token refresh automatically. When the access token expires, it uses your refresh token and client credentials to obtain a new one. You don't need to repeat this process.

## Scopes

The plugin requests these OAuth scopes:

| Scope | Purpose |
|---|---|
| `read` | Read public profile data |
| `activity:read_all` | Read all activities (including private ones) |

If you only want public activities, you can use `activity:read` instead of `activity:read_all`.
