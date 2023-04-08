[![Preview project](https://img.shields.io/static/v1?label=PHP&message=Preview&color=informational&style=flat&logo=php)][preview]
[![Preview project](https://img.shields.io/static/v1?label=Angular&message=Preview&color=critical&style=flat&logo=angular)][frontend_preview]

# Auction-Aucion-api

REST API of online auctions application "Auction Auction". [Click here to preview.][preview]

[Link to Figma frontend prototype.][figma_prototype]

[preview]: https://projekty.fullweb.net.pl/auction-auction-api
[frontend_preview]: https://projekty.fullweb.net.pl/auction-auction
[figma_prototype]: https://www.figma.com/proto/ehLDQSnWFPYfzypYKI0fDk/Online-Auctions?scaling=min-zoom&page-id=0%3A1&starting-point-node-id=57%3A219&node-id=57%3A219

[Frontend implementation preview.][frontend_preview]

[Frontend implementation repository.](https://github.com/j-fudali/auction-auction)

### Notes

Repository includes "depreciated-Auction-Auction-api-guidebook.pdf" file with endpoints descriptions and "Auction Auction API.postman_collection.json" with exported Postman collection consisting of available api requests.

---

### Technologies

- PHP 7.4.29
- MariaDB 10.4.24
- PHPMailer 6.6
- PHP-JWT 5.2
- Composer

---

### Features

- [x] User login
- [x] Login endpoint throttling (max 5 req. /30 sec.)
- [x] Logging login attempts
- [x] User registration
- [x] Email based user account activation
- [x] User profile
- [x] Password change and reset with global token expiration
- [x] Password reset token (15 min. expiration time)
- [x] JWT based authentication (30 min. expiration time for main JWT, 90 min. for refresh token)
- [x] Creating new auctions
- [x] Adding auctions to favourites
- [x] Filtering offers
- [x] Placing bids for offers
- [x] Generating .csv report of user owned, ended auctions (1 request per day)
- [x] Chat messages with images
- [x] Notifications system
- [x] Cron based pseudo task queue for generating and sending by email requested reports and closing auctions
- [x] Paggination

and more...

---

### Database relational diagram (depreciated)

![Auction Auction database diagram](/images/depreciated_auction-auction_db_diagram.png)
