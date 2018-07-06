#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <dirent.h>
#include <unistd.h>
#include <fcntl.h>
#include <math.h>
#include <sys/file.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <iostream>
#include <string>
#include <map>
#include "md5.h"
using namespace	std;
using std::string;
using std::map;

#define	MAX_PATH_LENGTH	512
#define	MAX_FILE_EXTENSION	9
//#define TARGET_PATH	"/home/www/nodertmp/video"
#define	MIN_FILE_SIZE	1024000	//最小文件体积1MB
#define	MAX_FILE_SIZE	1024000000	//最大文件体积1GB

unsigned long visit_dirs=0;
unsigned long visit_files=0;
map<string,	long> filesize;
string	serverUrl;	//服务器URL
string	account;	//SI用户账号
string	commkey;	//SI通信密码
string	md5str;
char	tmpstr[MAX_PATH_LENGTH+1];

void listdir(char *path){
	DIR			*ptr_dir; 
	struct dirent	*dir_entry;	 
	int			i =	0;	
	char		child_path[MAX_PATH_LENGTH];  
	char		file_path[MAX_PATH_LENGTH];	
	int		fd;
	struct stat	stbuf;
	string	filename;	//正在处理的文件名
	string	basename;	//不含扩展名的文件名
	string	targetfile;	//目标文件名
	size_t	old_size,new_size;	//录像文件上次扫描大小以及本次扫描大小，通过大小是否变化判断是否在录像
	string	cmd;	//暂存命令字串
	string	uri;	//参加MD5的部分网址
	string	tm;	//暂存SI通信包youxisi有效时间戳16进制字串
	int	rt;	//调用外部命令的返回值
	char	*pstream_name;	//流字串指针
	char	*cstr;	//字串指针

/*	  child_path = (char*)malloc(sizeof(char)*MAX_PATH_LENGTH);	 
	if(child_path == NULL){	 
		printf("allocate memory	for	path failed.\n");  
		return;	 
	}  
*/
	memset(child_path, 0, sizeof(char)*MAX_PATH_LENGTH);  
/*	
	file_path =	(char*)malloc(sizeof(char)*MAX_PATH_LENGTH);  
	if(file_path ==	NULL){	
		printf("allocate memory	for	file path failed.\n");	
		free(child_path);  
		child_path = NULL;	
		return;	 
	} 
*/
	memset(file_path, 0, sizeof(char)*MAX_PATH_LENGTH);	 
  
	ptr_dir	= opendir(path);  
	while((dir_entry = readdir(ptr_dir)) !=	NULL){
		if(dir_entry->d_type & DT_DIR){	
			//若是目录 
			if(strcmp(dir_entry->d_name,".") ==	0 ||  
			   strcmp(dir_entry->d_name,"..") == 0){  
				continue;  
			}  
			sprintf(child_path,	"%s/%s", path, dir_entry->d_name);	
			printf("[DIR]%s\n",	child_path);  
			visit_dirs++;  
			//listdir(child_path);	
		}  
  
		if(dir_entry->d_type & DT_REG){	
			//若是文件
			sprintf(file_path, "%s/%s",	path, dir_entry->d_name);
			printf("[FILE]%s\n", file_path);  
			filename=dir_entry->d_name;
			old_size=filesize[filename];

			fd=open(file_path,O_RDONLY);
			if(fd==-1){
				cout<<"Open	file failure.\n"<<endl;
				continue;
			}
			if ((fstat(fd, &stbuf) != 0) ||	(!S_ISREG(stbuf.st_mode))) {
				/* Handle error	*/
			}else{
				new_size=stbuf.st_size;
				sprintf(tmpstr,"%x",time(0)+60);	//60秒通信有效时间
				tm=tmpstr;
				cout<<"\tfile:"<<filename<<" old size:"<<old_size<<" new size:"<<new_size<<endl;
				if(old_size!=new_size){
					cout<<"\tupdate	activestream recording size."<<endl;
					filesize[filename]=new_size;	
					sprintf(tmpstr,	"/admin.php/SI/recordSize/stream/%s/size/%d",filename.c_str(),(int)ceil(new_size/1000000.0));
					uri=tmpstr;
					md5str=md5(commkey+uri+account+tm);
					cmd="curl --data \"account="+account+"&sec="+md5str+"&tm="+tm+"\" "+serverUrl+uri;
					rt=system(cmd.c_str());		//更新数据库
					cout<<cmd<<" return:"<<rt<<endl;
					//文件大于最大体积，重启一次录像，使得用另一个文件录像
					if(new_size>MAX_FILE_SIZE){
						cstr=new char [filename.length()+1];
						strcpy(cstr,filename.c_str());
						pstream_name=strtok(cstr,"-");		//从文件名中取流名称
						if(NULL!=pstream_name){
							cout<<"\tRestarting record...";
							sprintf(tmpstr,"curl \"http://localhost:8011/control/record/stop?app=live&rec=rec1&name=%s\" ",pstream_name);
							rt=system(tmpstr);
							sprintf(tmpstr,"curl \"http://localhost:8011/control/record/start?app=live&rec=rec1&name=%s\" ",pstream_name);
							rt=system(tmpstr);
							cout<<tmpstr<<" RETURN:"<<rt<<endl;
						}else{
							cout<<"\tFile name format error."<<endl;
						}
						//由于停止录像时录像文件大小会变化，需要重新读取
						fstat(fd, &stbuf);	//这样不知是否能读得文件的实时大小
						filesize[filename]=stbuf.st_size;
						
						delete[] cstr;
					}
				}else{
					cout<<"\ttrance	code to	mp4"<<endl;
					if(new_size<(size_t)MIN_FILE_SIZE ){
						//小于直接删除
						sprintf(tmpstr,	"%s/%s",path,filename.c_str());
						rt=remove(tmpstr);
						cout<<filename<<" size:	"<<new_size<<" remove:"<<rt<<endl; 
					}else{
						//执行外部转换批命令，(转成MP4并提取jpg)调用web服务接口增加录像记录
						basename=filename.substr(0,filename.rfind(".flv"));
						cmd="./trans2mp4.sh	"+basename;
						rt=system(cmd.c_str());
						//cout<<cmd<<rt<<endl;
					}

					//delete FLV and fileszie item
					sprintf(tmpstr,	"%s/%s",path,filename.c_str());
					rt=remove(tmpstr);
					cout<<"\tremove	"<<filename<<" rt="<<rt<<endl;
					filesize.erase(filename);	//delete item;
				}	//if(old_size!=new_size)
			}	//if ((fstat(fd, &stbuf) !=	0)
			close(fd);	
			visit_files++;	
		}  //if(dir_entry->d_type &	DT_REG)
	}  //while
	
	closedir(ptr_dir);
	//free(child_path);	 
	//child_path = NULL;  
  
	//free(file_path);	
	//file_path	= NULL;	 
}

#include <time.h>
int	main(int argc, char	*argv[]){
	time_t timesteamp;
	struct tm *	timeinfo;
	char buffer	[128];
	

	if(4!=argc){
		printf("Usage: trans <server url> <account>	<commkey>\n");
		exit(2);
	}
	serverUrl=argv[1];
	account=argv[2];
	commkey=argv[3];
printf("int	size=%d,%d\n",sizeof(int),sizeof(long));
	while(1){
		time (&timesteamp);
		timeinfo = localtime (&timesteamp);
		strftime (buffer,sizeof(buffer),"Now is	%Y/%m/%d %H:%M:%S",timeinfo);
		printf("\n======== %s =============\n",	buffer);

			listdir((char *)"/tmp/rec");
		sleep(10);
			listdir((char *)"/tmp/rec");  
		cout<<"File	numbers:"<<filesize.size()<<endl<<endl;
		sleep(10); 
	}
		 
	  
	return 0;  
}
