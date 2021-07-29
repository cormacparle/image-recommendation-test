alter table unillustratedArticles add ts timestamp not null default current_timestamp;
alter table imageRecommendations add ts timestamp not null default current_timestamp;