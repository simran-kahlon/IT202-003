CREATE TABLE IF NOT EXISTS `PointsHistory` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id int ,
    point_change int,
    reason varchar(15) not null,
    created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id),
)