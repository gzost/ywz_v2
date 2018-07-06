#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <dirent.h>
#include <unistd.h>
#include <fcntl.h>
#include <sys/file.h>
#include <sys/stat.h>
#include <sys/types.h>

#define MAX_PATH_LENGTH	512
#define MAX_FILE_EXTENSION	9

unsigned long visit_dirs=0;
unsigned long visit_files=0;

void listdir(char *path){
    DIR         *ptr_dir;  
    struct dirent   *dir_entry;  
    int         i = 0;  
    char        *child_path;  
    char        *file_path; 
    FILE	*fp;
    char	cando;
    int		rt;

    child_path = (char*)malloc(sizeof(char)*MAX_PATH_LENGTH);  
    if(child_path == NULL){  
        printf("allocate memory for path failed.\n");  
        return;  
    }  
    memset(child_path, 0, sizeof(char)*MAX_PATH_LENGTH);  
  
    file_path = (char*)malloc(sizeof(char)*MAX_PATH_LENGTH);  
    if(file_path == NULL){  
        printf("allocate memory for file path failed.\n");  
        free(child_path);  
        child_path = NULL;  
        return;  
    }  
    memset(file_path, 0, sizeof(char)*MAX_PATH_LENGTH);  
  
    ptr_dir = opendir(path);  
    while((dir_entry = readdir(ptr_dir)) != NULL){  
        if(dir_entry->d_type & DT_DIR){  
            if(strcmp(dir_entry->d_name,".") == 0 ||  
               strcmp(dir_entry->d_name,"..") == 0){  
                continue;  
            }  
  
            sprintf(child_path, "%s/%s", path, dir_entry->d_name);  
            printf("[DIR]%s\n", child_path);  
  
            visit_dirs++;  
  
            //listdir(child_path);  
        }  
  
        if(dir_entry->d_type & DT_REG){ 
	    sprintf(file_path, "%s/%s", path, dir_entry->d_name);
	    fp=fopen(file_path,"rb+");
	    rt=flock(fp->_fileno,LOCK_EX|LOCK_NB);
	    cando=(NULL==fp)?'N':'Y';
            fclose(fp);  
            printf("[FILE]%s %c%d\n", file_path,cando,rt);  
            visit_files++;  
        }  
    }  
  
    free(child_path);  
    child_path = NULL;  
  
    free(file_path);  
    file_path = NULL;  
}

int main(int argc, char *argv[]){
    if(argc == 2){  
        listdir(argv[1]);  
        printf("Total DIR: %ld, Total FILE: %ld\n", visit_dirs, visit_files);  
    }else{  
        printf("Usage: listdir <dir>\n");  
        return;  
    }  
      
    return 0;  
}
