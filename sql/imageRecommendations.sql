create table unillustratedArticles (
    id int not null auto_increment,
    langCode varchar(3) not null,
    pageTitle varchar(255) not null,
    primary key (id),
    unique key (langCode, pageTitle)
) engine innodb;

create table imageRecommendations (
    id int not null auto_increment,
    unillustratedArticleId int not null,
    resultFilePage varchar(255) not null,
    resultImageUrl varchar(255) not null,
    source varchar(255) default null,
    rating tinyint(1) default null,
    `sensitive` tinyint(1) default null,
    primary key (id),
    foreign key (unillustratedArticleId) references unillustratedArticles(id) on delete cascade
) engine innodb;