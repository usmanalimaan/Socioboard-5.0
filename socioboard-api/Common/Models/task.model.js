import db from '../Sequelize-cli/models/index.js'
import moment from 'moment'

const Operator = db.Sequelize.Op;
const userTeamJoinTable = db.join_table_users_teams;
const scheduleDetails = db.users_schedule_details;
const socialAccounts = db.social_accounts;
const teamSocialAccount = db.join_table_teams_social_accounts;
import AuthorizeServices from '../../Common/Services/authorize.services.js'
import FacebookConnect from '../../Common/Cluster/facebook.cluster.js'
import TwitterConnect from '../../Common/Cluster/twitter.cluster.js'
import LinkedInConnect from '../../Common/Cluster/linkedin.cluster.js'
import PinterestConnect from '../../Common/Cluster/pinterest.cluster.js'
import PublishedPost from '../Mongoose/models/publishedposts.js';
import DraftPost from '../Mongoose/models/draftedpostlists.js';
import TwitterInsightMongoModel from '../Mongoose/models/twitterInsights.js';

import PublishLibs from '../Models/publish.model.js';
import UserTeamAccount from '../Shared/userTeamAccounts.shared.js'

import AdminApprovalPostModels from '../Mongoose/models/adminapprovalposts.js';
import TaskModels from '../Mongoose/models/taskmodels.js';
import SchedulePostModels from '../Mongoose/models/scheduleposts.js';

import logger from '../../Publish/resources/Log/logger.log.js'
import config from 'config'
import schedule from 'node-schedule'
class TaskModel {

    constructor() {
        Object.assign(this, UserTeamAccount);
        this.authorizeServices = new AuthorizeServices(config.get('authorize'));
        this.facebookConnect = new FacebookConnect(config.get('facebook_api'));
        this.twitterConnect = new TwitterConnect(config.get('twitter_api'));
        this.linkedInConnect = new LinkedInConnect(config.get('linkedIn_api'), config.get('profile_add_redirect_url'));
        this.pinterestConnect = new PinterestConnect(config.get('pinterest'));
    }

    isTeamValidForUser(userId, teamId) {

        return new Promise((resolve, reject) => {
            return userTeamJoinTable.findOne({
                where: {
                    user_id: userId,
                    team_id: teamId,
                    left_from_team: false
                },
                attributes: ['id', 'user_id']
            })
                .then((result) => {
                    if (result) resolve();
                    else throw new Error("User not belongs to the team!");
                })
                .catch((error) => {
                    reject(error);
                });
        });


    }


    getTaskDetails(userId, teamId, pageId) {
        return new Promise((resolve, reject) => {
            if (!userId || !teamId || !pageId) {
                reject(new Error("Invalid Inputs"));
            } else {
                var skip = (pageId - 1) * config.get("perPageLimit");
                var taskModels = new TaskModels();
                var tasks = [];
                var postDetails = [];
                var scheduleInfos = [];
                var normalPostIds = [];
                var schedulePostIds = [];


                return this.isTeamValidForUser(userId, teamId)
                    .then(() => {
                        return taskModels.getTeamPublishTaskLists(userId, teamId, skip, config.get("perPageLimit"));
                    })
                    .then((result) => {
                        var PostIds = [];
                        if (result.length > 0) {
                            tasks = result;
                            result.map(element => {
                                if (element.type == 2)
                                    normalPostIds.push(element.normalPostId);
                                else
                                    schedulePostIds.push(element.schedulePostId);
                            });
                        }
                        return PostIds;
                    })
                    .then(() => {
                        if (normalPostIds.length > 0) {
                            var adminpostlists = new AdminApprovalPostModels();
                            return adminpostlists.getPostsById(normalPostIds)
                                .then((result) => {
                                    postDetails.push(...result);
                                })
                                .catch((error) => { throw error; });
                        }
                        return;
                    })
                    .then(() => {
                        if (schedulePostIds.length > 0) {
                            return scheduleDetails.findAll({
                                where: {
                                    schedule_id: schedulePostIds
                                }
                            })
                                .then((result) => {
                                    var mongoIds = [];
                                    if (result.length > 0) {
                                        scheduleInfos.push(...result);
                                        result.map(element => {
                                            mongoIds.push(element.mongo_schedule_id);
                                        });
                                        return mongoIds;
                                    } else
                                        return mongoIds;
                                })
                                .then((mongoIds) => {
                                    if (mongoIds.length > 0) {
                                        var schedulePostModels = new SchedulePostModels();
                                        return schedulePostModels.getPostsById(mongoIds);
                                    } else
                                        return mongoIds;
                                })
                                .then((result) => {
                                    postDetails.push(...result);
                                    return;
                                })
                                .catch((error) => { throw error; });
                        }
                        return;
                    })
                    .then(() => {
                        resolve({ code: 200, status: "success", data: { count: tasks.length, tasks: tasks, schedule_details: scheduleInfos, post_details: postDetails } });
                    })
                    .catch((error) => {
                        reject(error);
                    });
            }
        });
    }


    assignTask(userId, taskId, assigningUserId, teamId) {
        return new Promise((resolve, reject) => {
            if (!userId || !taskId || !assigningUserId || !teamId) {
                reject(new Error("Invalid Inputs"));
            }
            else if (userId == assigningUserId) {
                reject(new Error("Cant able to assign the task to yourself!"));
            }
            else {
                return this.isUserMemberOfATeam(assigningUserId, teamId)
                    .then((isMember) => {
                        if (isMember) {
                            var taskModels = new TaskModels();
                            return taskModels.assignTask(userId, taskId, assigningUserId, teamId)
                                .then((result) => {
                                    if (!result) {
                                        throw new Error("Sorry, Process failed due to any one of the reason: 1.Invalid TaskId,\n\r 2.Task didnt created for specified team,\n\r 3.Cant able to assign the task to post owner,\n\r 4.Task cant able to assign after solved or rejected.");
                                    } else
                                        resolve({ code: 200, status: "success", data: "Task assigned successfully!" });
                                })
                                .catch((error) => { throw error; });
                        } else {
                            throw new Error("Requested user isnt member of the team!");
                        }
                    })
                    .catch((error) => { reject(error); });
            }
        });
    }


    isUserMemberOfATeam(userId, teamId) {
        return new Promise((resolve, reject) => {
            if (!userId || !teamId) {
                reject(new Error('Invalid Inputs'));
            } else {
                return userTeamJoinTable.findOne({
                    where: {
                        [Operator.and]: [{
                            user_id: userId
                        }, {
                            team_id: teamId
                        }, {
                            [Operator.not]: {
                                left_from_team: true
                            }
                        }]
                    },
                    attributes: ['id', 'user_id', 'team_id', 'permission']
                })
                    .then((user) => {
                        resolve(user ? true : false);
                    })
                    .catch((error) => {
                        reject(error);
                    });
            }
        });
    }

    updateTaskStatus(userId, taskId, status, teamId) {
        status = status ? String(status).toLowerCase() : null;
        return new Promise((resolve, reject) => {
            var validStatus = ["approved", "rejected"];
            if (!userId || !taskId || !status || !teamId)
                reject(new Error("Invalid Inputs"));
            else if (!validStatus.includes(status))
                reject(new Error("Invalid Status"));
            else {
                var taskModels = new TaskModels();
                var taskResult = {};
                return taskModels.updatePublishTaskStatus(userId, taskId, teamId, status)
                    .then((result) => {
                        if (!result)
                            throw new Error("Sorry, Process failed due to any one of the reason: 1.Invalid TaskId,\n\r 2.Task didnt created for specified team,\n\r 3.You dont have access to change the status,\n\r 4.May be status already with update with same value.");
                        else {
                            taskResult = result;
                            var savedId = taskResult.type == 2 ? taskResult.normalPostId : taskResult.schedulePostId;
                            if (taskResult.type == 2) {
                                var adminpostlists = new AdminApprovalPostModels();
                                return adminpostlists.updateAdminResponse(savedId, status);
                            } else if (taskResult.type == 3) {
                                return scheduleDetails.findOne({ where: { schedule_id: savedId } })
                                    .then((result) => {
                                        if (!result)
                                            throw new Error("Invalid id to fetch the schedule details");
                                        else {
                                            var schedulePost = new SchedulePostModels();
                                            return schedulePost.updateAdminResponse(result.mongo_schedule_id, status);
                                        }
                                    })
                                    .then(() => {
                                        var scheduleStatus = status != "approved" ? 4 : 1;
                                        return scheduleDetails.update({ schedule_status: scheduleStatus }, { where: { schedule_id: savedId } });
                                    })
                                    .catch((error) => { throw error; });

                            } else {
                                throw new Error("Task type isnt for publishing!");
                            }
                        }
                    })
                    .then(() => {
                        if (status != "approved")
                            resolve({ code: 200, status: "success", data: "Task has been rejected!" });
                        else {
                            var taskType = taskResult.type;
                            var mongoId = taskType == 2 ? taskResult.normalPostId : taskResult.schedulePostId;
                            if (taskType && mongoId) {
                                return this.performTask(taskId, taskType, mongoId, teamId);
                            } else
                                throw new Error("Invalid task type to perform an action");
                        }
                    })
                    .then((result) => {
                        result.data = "Task has been approved!";
                        resolve(result);
                    })
                    .catch((error) => { reject(error); });
            }
        });
    }

    performTask(taskId, taskType, postSavedId, teamId) {
        return new Promise((resolve, reject) => {
            if (!taskId || !taskType || !postSavedId) {
                reject(new Error("Invalid taskId"));
            } else {
                switch (Number(taskType)) {
                    case 2:
                        return this.afterPublishNowApproved(postSavedId, teamId)
                            .then((result) => resolve(result))
                            .catch((error) => reject(error));
                    case 3:
                        return this.afterScheduleApproved(postSavedId, teamId)
                            .then((result) => resolve(result))
                            .catch((error) => reject(error));
                    default:
                        break;
                }
            }
        });
    }

    afterPublishNowApproved(postSavedId, teamId) {
        return new Promise((resolve, reject) => {
            if (!postSavedId || !teamId) {
                reject(new Error("Invalid Inputs"));
            } else {
                var adminpostlists = new AdminApprovalPostModels();
                return adminpostlists.getUnpublishedPostById(postSavedId)
                    .then((result) => {
                        if (!result) {
                            throw new Error("Sorry, Post has been published already!");
                        } else {
                            logger.info(`Fetch post details : ${JSON.stringify(result)}`);
                            var postDetails = {
                                message: result.description,
                                mediaPath: result.mediaUrl,
                                link: result.shareLink,
                                postType: result.postType,
                                mongoScheduleId: "Na",
                                moduleName: "Direct Post",
                                boardDetails: result.pinBoards,
                                ownerId: result.ownerId,
                            };
                            if (!result.description) {
                                throw new Error("Cant able to fetch post details");
                            } else {
                                var publishLibs = new PublishLibs();
                                return publishLibs.startPublish(postDetails, teamId, result.accountIds);
                            }
                        }
                    })
                    .then((details) => {
                        logger.info(details);
                        return adminpostlists.updatePublishStatus(postSavedId);
                    })
                    .then(() => {
                        resolve({ code: 200, status: 'success' });
                    })
                    .catch(error => { reject(error); });
            }
        });
    }

    afterScheduleApproved(postSavedId) {
        return new Promise((resolve, reject) => {
            if (!postSavedId) {
                reject(new Error("Invalid Inputs"));
            } else {
                return scheduleDetails.findOne({
                    where: {
                        schedule_id: postSavedId,
                        schedule_status: { [Operator.eq]: 1 }
                    }
                })
                    .then((result) => {
                        if (!result) {
                            throw new Error("Sorry, Schedule details arent in ready state, may be in pause or done state!");
                        } else {
                            logger.info(`Fetch post details : ${JSON.stringify(result)}`);

                            var schedulePostModels = new SchedulePostModels();
                            return schedulePostModels.getScheduleDetails(result.mongo_schedule_id)
                                .then((scheduleMongoDetails) => {

                                    logger.info(`Schedule details : ${JSON.stringify(scheduleMongoDetails)}`);

                                    if (!scheduleMongoDetails)
                                        throw new Error("Schedule details not present!");
                                    else if (result.schedule_type == 0) {
                                        var scheduleTimings = [];
                                        scheduleTimings.push(scheduleMongoDetails.normalScheduleDate);
                                        return this.getRunningDayAndTodayTimings(scheduleTimings)
                                            .then((result) => {
                                                logger.info(`Timings : ${JSON.stringify(result.todaysTiming)}`);
                                                return result.todaysTiming;
                                            })
                                            .catch((error) => {
                                                throw new Error("Cant able to get the schedule timings!");
                                            });
                                    }
                                    else if (result.schedule_type == 1) {
                                        return this.getRunningDayAndTodayTimings(scheduleMongoDetails.daywiseScheduleTimer)
                                            .then((result) => {
                                                logger.info(`Timings : ${JSON.stringify(result.todaysTiming)}`);
                                                return result.todaysTiming;
                                            })
                                            .catch((error) => {
                                                throw new Error("Cant able to get the schedule timings!");
                                            });
                                    } else {
                                        throw new Error(`Invalid schedule status and status is ${scheduleMongoDetails.schedule_status}!`);
                                    }
                                });
                        }
                    })
                    .then((todaysTiming) => {
                        if (todaysTiming.length > 0) {
                            var scheduleBase = new ScheduleBase();
                            return scheduleBase.makeSchedule(postSavedId, todaysTiming)
                                .then((response) => {
                                    resolve({ code: 200, status: "success", scheduleInfo: response });
                                })
                                .catch((response) => {
                                    resolve({ code: 200, status: "success", expiredTimings: response.expiredTimes });
                                });
                        }
                        else {
                            resolve({ code: 200, status: "success" });
                        }
                    })
                    .catch(error => { reject(error); });
            }
        });
    }

    getRunningDayAndTodayTimings(scheduleTimer) {
        return new Promise((resolve, reject) => {
            if (!scheduleTimer && scheduleTimer.length == 0) {
                reject(new Error("Invalid Timers"));
            } else {
                var currentDayOfWeek = moment.utc().day();
                // schedulePart : 0 => First half (0-11) 
                // schedulePart : 1=> Second half (12-23)
                var schedulePart = moment.utc().hour() > 11 ? 1 : 0;

                logger.info(`Utc hours : ${moment.utc().hour()}`);
                logger.info(`Schedule timing : ${JSON.stringify(scheduleTimer)}`);

                var todaysTiming = [];
                var runningDays = null;
                scheduleTimer.forEach((runningDay) => {
                    if (runningDay.dayId == undefined || runningDay.dayId == null) {
                        if (moment().utc().startOf('day').isSame(moment(runningDay).utc().startOf('day')))
                            todaysTiming.push(runningDay);
                    } else {
                        if (runningDay.dayId == currentDayOfWeek) {
                            runningDay.timings.forEach(time => {
                                logger.info(`Schedule timing hours : ${moment(time).utc().hour()}`);
                                var timesNoon = moment(time).utc().hour() > 11 ? 1 : 0;
                                if (timesNoon == schedulePart) {
                                    todaysTiming.push(time);
                                }
                            });
                        }
                        if (!runningDays)
                            runningDays = String(runningDay.dayId);
                        else
                            runningDays += `:${runningDay.dayId}`;
                    }
                });
                resolve({ runningDays: runningDays, todaysTiming: todaysTiming });
            }
        });
    }

    setupTwitterInsightsCrons() {
        schedule.scheduleJob('00 22 * * *', () => {
            logger.info(`Cron started for updating all twitter account insight's which is not locked for atleast a team, started time ${moment()}`);
            new Promise((resolve, reject) => {
                var twtAccount = null;
                return socialAccounts.findAll({
                    where: { account_type: 4 }, raw: 1
                })
                    .then((result) => {
                        if (result.length > 0) {
                            return Promise.all(result.map(socialAccount => {
                                twtAccount = socialAccount;
                                return teamSocialAccount.findOne({
                                    where: {
                                        account_id: socialAccount.account_id,
                                        is_account_locked: 0
                                    }
                                })
                                    .then((runnigAccount) => {
                                        logger.info(`RunnigAccount ${runnigAccount.dataValues.account_id, socialAccount.user_name}`);
                                        if (runnigAccount) {
                                            var accountId = runnigAccount.dataValues.account_id;
                                            var updatedTwitterStats = {};
                                            return this.twitterConnect.getLookupList(socialAccount.access_token, socialAccount.refresh_token, socialAccount.user_name)
                                                .then((updatedProfileDetails) => {
                                                    updatedTwitterStats = updatedProfileDetails;
                                                    return this.createOrUpdateFriendsList(accountId, updatedProfileDetails);
                                                })
                                                .then(() => {
                                                    logger.info(`Updated twitter insight for ${socialAccount.user_name}`);
                                                    var insightObject = {
                                                        followerCount: updatedTwitterStats.follower_count,
                                                        followingCount: updatedTwitterStats.following_count,
                                                        favouritesCount: updatedTwitterStats.favorite_count,
                                                        postsCount: updatedTwitterStats.total_post_count,
                                                        userMentions: updatedTwitterStats.user_mentions,
                                                        retweetCount: updatedTwitterStats.retweet_count
                                                    };
                                                    var twtMongomodelObject = new TwitterInsightMongoModel();
                                                    return twtMongomodelObject.addInsights(accountId, insightObject);
                                                })
                                                .catch((error) => {
                                                    logger.error(error);
                                                });
                                        }
                                        else {
                                            logger.error(error.message);
                                        }
                                    })
                                    .catch((error) => {
                                        logger.error(error.message);
                                    });
                            }))
                                .then(() => {
                                    // resolve("success");
                                })
                                .catch((error) => {
                                    logger.error(error.message);
                                });
                        }
                        else
                            logger.error(error.message);
                    })
                    .catch((error) => {
                        logger.error(error.message);
                    });
            });
        });
        logger.info("Cron setup completed for twitter insights services...");

    }
}

export default TaskModel