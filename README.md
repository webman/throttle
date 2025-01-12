## About
The purpose of the current fork are dockerizing app [asherkin/throttle](https://github.com/asherkin/throttle) and adapt for local usage. Pay attention, it should not be used in production.

Dockerized app is part of another project [throttle-dockerized](https://github.com/webman/throttle-dockerized). For more information, see details there.

## Changes
- Fixed application bootstrap with broken library `phacility/libphutil`
- Added ngrok free urls to trusted hosts
- Replaced credentials for usage with another docker containers
- Enabled debugging
- Removed authorization checks for fast local usage experience

## Credits
- [asherkin](https://github.com/asherkin/)
